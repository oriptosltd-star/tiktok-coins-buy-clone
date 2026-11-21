<script>
// Loyalty Widget Functions
async function loadLoyaltyData() {
    try {
        const res = await fetch('loyalty_api.php?action=get_points');
        const data = await res.json();
        if (data.success) {
            document.getElementById('totalPoints').textContent = data.total_points.toLocaleString();
            document.getElementById('lifetimePoints').textContent = data.lifetime_points.toLocaleString();
            document.getElementById('availablePoints').textContent = data.total_points.toLocaleString();
            const tierBadge = document.getElementById('tierBadge');
            const icons = { 
                bronze: '<i class="ri-medal-fill"></i>', 
                silver: '<i class="ri-medal-2-fill"></i>', 
                gold: '<i class="ri-vip-crown-fill"></i>', 
                platinum: '<i class="ri-vip-diamond-fill"></i>' 
            };
            tierBadge.innerHTML = `${icons[data.tier]} ${data.tier.charAt(0).toUpperCase() + data.tier.slice(1)}`;
            tierBadge.className = `tier-badge ${data.tier}`;
            document.getElementById('pointsMultiplier').textContent = data.benefits.points_multiplier + 'x';
            document.getElementById('tierDiscount').textContent = data.benefits.discount + '%';
        }
    } catch (e) { console.error('Loyalty load error:', e); }
}

function openRedeemModal() { 
    document.getElementById('redeemModal').classList.add('show'); 
}

function closeRedeemModal() { 
    document.getElementById('redeemModal').classList.remove('show'); 
}

async function redeemPoints(pts, type) {
    const fd = new FormData();
    fd.append('action', 'redeem_points');
    fd.append('points', pts);
    fd.append('reward_type', type);
    try {
        const res = await fetch('loyalty_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            alert(`Success! Redeemed ${pts} points for $${data.discount} discount!`);
            closeRedeemModal();
            loadLoyaltyData();
        } else {
            alert(data.error || 'Redemption failed');
        }
    } catch (e) { alert('Failed to redeem points'); }
}

function showEarnOptions() {
    alert('Ways to earn points:\n\n• Make a purchase: 10 points per $1\n• Daily login: 5 points\n• Refer a friend: 200 points\n• Friend makes first purchase: 500 points');
}

// Load loyalty data after page loads
setTimeout(() => { loadLoyaltyData(); }, 2000);
</script>

/**
 * Site-Wide Referral Access Control
 * 
 * This script protects all pages except app.html
 * Redirects non-referral users to app.html (coin purchase only)
 * Allows full site access to users with valid referral codes
 */

(async function() {
    'use strict';
    
    // Don't run on app.html (publicly accessible)
    if (window.location.pathname.includes('app.html')) {
        console.log('📄 app.html - Publicly accessible');
        return;
    }
    
    console.log('🔐 Referral Guard: Checking access...');
    
    /**
     * Validate referral code with backend
     */
    async function validateReferralCode(code) {
        try {
            const response = await fetch(`check_referral.php?code=${encodeURIComponent(code)}`);
            const data = await response.json();
            return data.valid === true;
        } catch (err) {
            console.error('❌ Validation error:', err);
            return false;
        }
    }
    
    /**
     * Check if user has valid referral access
     */
    async function checkReferralAccess() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        
        // If referral code in URL, validate it
        if (refCode) {
            console.log('🔍 Validating referral code:', refCode);
            const isValid = await validateReferralCode(refCode);
            
            if (isValid) {
                // Save valid referral code
                localStorage.setItem('referralCode', refCode);
                localStorage.setItem('hasReferralAccess', 'true');
                
                // Set cookie for 30 days
                const d = new Date();
                d.setTime(d.getTime() + (30*24*60*60*1000));
                document.cookie = `ref_code=${refCode};expires=${d.toUTCString()};path=/`;
                
                console.log('✅ Valid referral code - Full site access granted');
                return true;
            } else {
                // Invalid code
                localStorage.removeItem('referralCode');
                localStorage.removeItem('hasReferralAccess');
                console.log('❌ Invalid referral code');
                return false;
            }
        }
        
        // Check if previously validated and saved
        const savedCode = localStorage.getItem('referralCode');
        if (savedCode && localStorage.getItem('hasReferralAccess') === 'true') {
            console.log('🔍 Re-validating saved code:', savedCode);
            const isValid = await validateReferralCode(savedCode);
            
            if (isValid) {
                console.log('✅ Saved referral code still valid');
                return true;
            } else {
                // Saved code no longer valid
                localStorage.removeItem('referralCode');
                localStorage.removeItem('hasReferralAccess');
                console.log('❌ Saved referral code expired');
                return false;
            }
        }
        
        return false;
    }
    
    // Check access
    const hasAccess = await checkReferralAccess();
    
    if (!hasAccess) {
        console.log('🚫 No valid referral - Redirecting to app.html');
        console.log('💡 To access full site, visit with: ?ref=YOUR_VALID_CODE');
        
        // Redirect to app.html (coin purchase page)
        window.location.href = '/app.html';
    } else {
        console.log('✅ Full site access granted');
    }
})();

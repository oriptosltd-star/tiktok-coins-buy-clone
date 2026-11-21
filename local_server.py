import http.server
import socketserver
import json
import urllib.request
import urllib.error
import os

PORT = 8000

class MyHandler(http.server.SimpleHTTPRequestHandler):
    def do_POST(self):
        # Intercept auth.php for local testing (MOCK AUTH)
        if self.path == '/auth.php':
            self.send_response(303) # See Other (Redirect)
            self.send_header('Location', 'dashboard.html')
            self.end_headers()
            return

        # Intercept the PHP backend call
        if self.path == '/create_payment.php':
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            
            try:
                input_data = json.loads(post_data.decode('utf-8'))
                
                # Logic from create_payment.php converted to Python
                amount_usd = float(input_data.get('amount', 0))
                product_name = input_data.get('productName', 'Coins Bundle')
                
                if amount_usd <= 0:
                    self.send_response(400)
                    self.send_header('Content-type', 'application/json')
                    self.end_headers()
                    self.wfile.write(json.dumps({'error': 'Invalid amount'}).encode())
                    return

                # WOOSHPAY CONFIG
                # TODO: User must set this key in this file too or I just copy the expectation
                api_key = 'sk_live_NTE4NDY1NTUyMjM3NTQxNDk4ODkxOnVnaWFaeTIwcmZRb05vV2M0N3FIM0VIbDE3MjkwODgwNDczNDU' 
                api_url = 'https://api.wooshpay.com/v1/checkout/sessions'
                
                # 1 USD = 87 INR
                exchange_rate = 87
                amount_inr = amount_usd * exchange_rate
                amount_smallest_unit = int(round(amount_inr * 100))
                
                host = self.headers.get('Host')
                protocol = 'http' # Localhost is usually http
                
                payload = {
                    "cancel_url": f"{protocol}://{host}/app.html",
                    "mode": "payment",
                    "success_url": f"{protocol}://{host}/app.html?payment=success",
                    "payment_method_types": ["upi"],
                    "line_items": [
                        {
                            "price_data": {
                                "currency": "INR",
                                "unit_amount": amount_smallest_unit,
                                "product_data": {
                                    "name": product_name,
                                    "description": f"Purchase of {product_name} (${amount_usd:.2f})"
                                }
                            },
                            "quantity": 1
                        }
                    ]
                }
                
                # Make Request to WooshPay
                req = urllib.request.Request(api_url, data=json.dumps(payload).encode('utf-8'))
                req.add_header('Authorization', f'Bearer {api_key}')
                # Use Basic Auth if Bearer fails (uncomment below if needed, but matching create_payment.php logic is key)
                # base64string = base64.b64encode(f'{api_key}:'.encode()).decode()
                # req.add_header('Authorization', f'Basic {base64string}')

                req.add_header('Content-Type', 'application/json')
                
                with urllib.request.urlopen(req) as response:
                    res_body = response.read()
                    
                    self.send_response(200)
                    self.send_header('Content-type', 'application/json')
                    self.end_headers()
                    
                    # Pass through the URL from WooshPay
                    api_response = json.loads(res_body)
                    if 'url' in api_response:
                        self.wfile.write(json.dumps({'url': api_response['url']}).encode())
                    else:
                         self.wfile.write(res_body)
                         
            except urllib.error.HTTPError as e:
                self.send_response(500)
                self.send_header('Content-type', 'application/json')
                self.end_headers()
                
                err_content = e.read().decode()
                self.wfile.write(json.dumps({'error': 'API Request Failed', 'details': err_content}).encode())
                
            except Exception as e:
                self.send_response(500)
                self.send_header('Content-type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps({'error': str(e)}).encode())
                
        else:
            self.send_error(404, "File not found")

    def do_GET(self):
        # MOCK DASHBOARD API
        if self.path == '/dashboard_api.php':
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            
            mock_data = {
                'user': {
                    'username': 'LocalTestUser',
                    'email': 'local@test.com',
                    'code': 'LOCAL123'
                },
                'balance': 1234.56,
                'stats': {
                    'clicks': 99,
                    'referrals': 12,
                    'total_earnings': 1234.56
                },
                'transactions': [
                    {'date': '2026-01-27 10:00:00', 'user': 'demo**@gmail.com', 'amount': '$100.00', 'comm': '$25.00', 'status': 'completed'},
                    {'date': '2026-01-26 15:30:00', 'user': 'test**@yahoo.com', 'amount': '$50.00', 'comm': '$12.50', 'status': 'pending'}
                ]
            }
            self.wfile.write(json.dumps(mock_data).encode())
            return
            
        return http.server.SimpleHTTPRequestHandler.do_GET(self)

print(f"Starting local server at http://localhost:{PORT}")
print("Open your browser to: http://localhost:8000/app.html")
print("Press Ctrl+C to stop.")

with socketserver.TCPServer(("", PORT), MyHandler) as httpd:
    httpd.serve_forever()

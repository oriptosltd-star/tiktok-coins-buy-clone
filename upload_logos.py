import ftplib
import os

# FTP CONFIGURATION
FTP_HOST = "82.25.83.158"
FTP_USER = "u916646072.cheaper360.store"
FTP_PASS = "@Idea241@"

def upload_crypto_logos():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Connected and Logged In!")
        
        # Change to img directory
        try:
            ftp.cwd('img')
            print("Changed to img directory")
        except:
            print("img directory doesn't exist, creating it...")
            ftp.mkd('img')
            ftp.cwd('img')
        
        # Upload crypto logos
        logos = ['usdt.png', 'btc.png', 'eth.png']
        
        for logo in logos:
            local_file = os.path.join('img', logo)
            if os.path.exists(local_file):
                print(f"Uploading {logo}...")
                with open(local_file, "rb") as f:
                    ftp.storbinary(f"STOR {logo}", f)
                print(f"✓ {logo} uploaded successfully")
            else:
                print(f"✗ {logo} not found locally")
        
        print("\n[SUCCESS] Crypto logos uploaded!")
        ftp.quit()
        
    except Exception as e:
        print(f"\n[ERROR] Upload Failed: {e}")

if __name__ == "__main__":
    upload_crypto_logos()

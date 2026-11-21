import ftplib

FTP_HOST = "82.25.83.158"
FTP_USER = "u916646072.cheaper360.store"
FTP_PASS = "@Idea241@"

def debug_ftp():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Connected!")
        
        print("\nCurrent Directory:")
        ftp.retrlines('PWD')
        
        print("\nFile List:")
        ftp.retrlines('LIST')
        
        ftp.quit()
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    debug_ftp()

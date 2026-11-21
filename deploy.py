import os
import ftplib
import sys

# FTP CONFIGURATION
FTP_HOST = "82.25.83.158"
FTP_USER = "u916646072.cheaper360.store"
FTP_PASS = "@Idea241@" # Confirmed by user
FTP_DIR = "" # Assume we are already in the right folder

# Files to exclude from upload
EXCLUDE_DIRS = {'.git', '.vscode', '__pycache__', '.gemini'} # Removed 'img' to allow image updates 
EXCLUDE_FILES = {'deploy.py', 'local_server.py', 'README.md', 'DEPLOYMENT.md', 'task.md', 'implementation_plan.md'}

def upload_files():
    try:
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("Connected and Logged In!")
        
        ftp.cwd(FTP_DIR)
        print(f"Changed directory to {FTP_DIR}")
        
        # Walk local directory
        local_root = os.getcwd()
        
        for root, dirs, files in os.walk(local_root):
            # Filtering excluded directories
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            
            # Calculate relative path for FTP structure
            rel_path = os.path.relpath(root, local_root)
            if rel_path == '.':
                rel_path = ''
            
            for file in files:
                if file in EXCLUDE_FILES or file.endswith('.py'):
                    continue
                    
                local_file = os.path.join(root, file)
                remote_file = os.path.join(rel_path, file).replace("\\", "/") 
                
                if remote_file.startswith('/'):
                    remote_file = remote_file[1:]

                print(f"Uploading {file}...")
                with open(local_file, "rb") as f:
                    ftp.storbinary(f"STOR {remote_file}", f)
        
        print("\n[SUCCESS] Deployment Complete!")
        ftp.quit()
        
    except Exception as e:
        print(f"\n[ERROR] Deployment Failed: {e}")
        print("Please check your FTP Password in deploy.py")

if __name__ == "__main__":
    upload_files()

@echo off
net session >nul 2>&1
if %errorLevel% neq 0 (
  echo Please right-click Run as administrator
  pause
  exit /b 1
)
:: Point hosts at Windows WSL vEthernet gateway; requires portproxy to WSL nginx
:: See HOST-ACCESS.md and C:\Users\Public\xmt-portproxy.bat
set XMT_HOST_IP=192.168.16.1
findstr /C:"xmt.wsl" %SystemRoot%\System32\drivers\etc\hosts >nul 2>&1
if %ERRORLEVEL%==0 (
  echo Updating existing XMT lines...
  findstr /V /C:"xmt.wsl" /C:"xmt.pub" /C:"zhubao.wsl" /C:"zhubao.pub" /C:"airobotor" /C:"hmos.wsl" /C:"hm-os" /C:"kstudy" /C:"drupalcn" /C:"drupal.org.cn" /C:"itra.wsl" /C:"itra.com.cn" %SystemRoot%\System32\drivers\etc\hosts > %TEMP%\hosts.tmp
  move /Y %TEMP%\hosts.tmp %SystemRoot%\System32\drivers\etc\hosts
)
echo.>> %SystemRoot%\System32\drivers\etc\hosts
echo # XMT Drupal multisite WSL (via Windows 192.168.16.1 portproxy)>> %SystemRoot%\System32\drivers\etc\hosts
echo %XMT_HOST_IP% xmt.wsl zhubao.wsl airobotor.wsl hmos.wsl kstudy.wsl drupalcn.wsl itra.wsl>> %SystemRoot%\System32\drivers\etc\hosts
echo %XMT_HOST_IP% xmt.pub zhubao.pub airobotor.com hm-os.com hm-os.cn kstudy.com.cn drupal.org.cn itra.com.cn>> %SystemRoot%\System32\drivers\etc\hosts
echo Done. Open http://xmt.wsl/ in browser
echo Ensure portproxy is set: run C:\Users\Public\xmt-portproxy.bat as Admin
pause

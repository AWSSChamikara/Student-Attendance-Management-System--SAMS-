@echo off
echo =========================================================================
echo  Student Attendance Management System (SAMS)
echo  Rajarata University of Sri Lanka (RUSL)
echo  Faculty of Applied Sciences (FAS) - Department of Computing
echo =========================================================================
echo.
echo Starting PHP Development Web Server...
echo Web Application is accessible at: http://localhost:8000
echo.
echo Press Ctrl+C at any time to stop the server.
echo =========================================================================
echo.

"C:\Users\awssc\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:8000 -t "%~dp0public"
pause

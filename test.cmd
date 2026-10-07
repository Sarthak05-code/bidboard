@echo off
color 0A
title BidBoard Matrix Terminal Diagnostic
cls

echo.
echo  ██████╗ ██╗██████╗ ██████╗ ██╗  ██╗██████╗ ██████╗ 
echo  ██╔══██╗██║██╔══██╗██╔══██╗██║  ██║██╔══██╗██╔══██╗
echo  ██████╔╝██║██║  ██║██████╔╝███████║██║  ██║██████╔╝
echo  ██╔══██╗██║██║  ██║██╔══██╗██╔══██║██║  ██║██╔══██╗
echo  ██████╔╝██║██████╔╝██████╔╝██║  ██║██████╔╝██║  ██║
echo  ╚═════╝ ╚═╝╚═════╝ ╚═════╝ ╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝
echo                      SYSTEM ENGINE ONLINE
echo.
timeout /t 2 >nul

:: Simulated Matrix Rain Loop
setlocal EnableDelayedExpansion
for /l %%i in (1,1,30) do (
    set "line="
    for /l %%j in (1,1,60) do (
        set /a "rand=!random! %% 2"
        set "line=!line!!rand!"
    )
    echo !line!
    timeout /t 1 >nul
)

echo.
echo [!] BidBoard Polyglot Engine Check Completed cleanly.
pause
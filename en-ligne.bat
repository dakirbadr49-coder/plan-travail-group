@echo off
rem Met Suivi Rosalie en ligne : serveur PHP limité au dossier public/ + tunnel Cloudflare.
rem Le lien https://....trycloudflare.com s'affiche ci-dessous. Fermer cette fenêtre coupe l'accès.
cd /d "%~dp0"
if not exist cloudflared.exe (
    echo cloudflared.exe introuvable : voir la section "Acces pour toute l'equipe" du README.
    pause
    exit /b 1
)
start "Suivi Rosalie - serveur PHP" /min C:\wamp64\bin\php\php8.3.28\php.exe -S 127.0.0.1:8090 -t "%~dp0public"
cloudflared.exe tunnel --url http://127.0.0.1:8090

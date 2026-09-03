$ErrorActionPreference = 'Stop'
$ROOT_DIR = $PSScriptRoot

Write-Host "🚀 Launching DM-Tech Digital Fortress (Windows PowerShell Mode)..." -ForegroundColor Cyan

$ACTIVE_PORT = Read-Host "Which Port are you actively coding on? (3000-3005 or NONE)"

# 1. Start Backend
Write-Host "Starting Backend on Port 8000..." -ForegroundColor Yellow
Set-Location -Path "$ROOT_DIR\backend"
Start-Process -NoNewWindow -FilePath "C:\xampp\php\php.exe" -ArgumentList "artisan serve --port=8000 --no-reload"

# Function to setup and launch a port natively
function Launch-Port {
    param(
        [string]$APP_DIR,
        [string]$PORT
    )
    
    $ISOLATION_DIR = Join-Path -Path $env:TEMP -ChildPath "dmtech-isolation\$PORT"
    if (!(Test-Path -Path $ISOLATION_DIR)) {
        New-Item -ItemType Directory -Path $ISOLATION_DIR | Out-Null
    }
    
    if ($PORT -eq $ACTIVE_PORT) {
        Write-Host "▶️ 🔥 Booting $APP_DIR on Port $PORT (ACTIVE DEV MODE)..." -ForegroundColor Green
        Set-Location -Path "$ROOT_DIR\$APP_DIR"
        
        $env:TMPDIR = $ISOLATION_DIR
        $env:NEXT_DIST_DIR = ".next-$PORT"
        $env:NEXT_PUBLIC_PORT = $PORT
        $env:PORT = $PORT
        $env:__NEXT_PRIVATE_PREBUNDLED_REACT = "1"
        $env:NEXT_PRIVATE_WORKER = "1"
        $env:NODE_OPTIONS = "--title=dm-tech-port-$PORT --max-old-space-size=1024"
        
        Start-Process -NoNewWindow -FilePath "npx.cmd" -ArgumentList "next dev -p $PORT"
    } else {
        Write-Host "▶️ 🧊 Booting $APP_DIR on Port $PORT (PRODUCTION MODE)..." -ForegroundColor Blue
        Set-Location -Path "$ROOT_DIR\$APP_DIR"
        
        $env:TMPDIR = $ISOLATION_DIR
        
        # Check for missing manifests
        if (!(Test-Path -Path ".next-$PORT\prerender-manifest.json")) {
            Write-Host "   (Missing Build Manifests - Executing Clean Build...)" -ForegroundColor Yellow
            $env:NODE_ENV = "production"
            $env:NEXT_DIST_DIR = ".next-$PORT"
            # Start-Process -NoNewWindow -Wait -FilePath "npx.cmd" -ArgumentList "next build --webpack"
        }

        if (Test-Path -Path ".next-$PORT\standalone\$APP_DIR\server.js") {
            Write-Host "   (Detected Standalone Build - Launching via Node...)"
            Copy-Item -Path "public\*" -Destination ".next-$PORT\standalone\$APP_DIR\public\" -Recurse -Force -ErrorAction SilentlyContinue
            Copy-Item -Path ".next-$PORT\static\*" -Destination ".next-$PORT\standalone\$APP_DIR\.next-$PORT\static\" -Recurse -Force -ErrorAction SilentlyContinue
            
            $env:PORT = $PORT
            $env:HOSTNAME = "0.0.0.0"
            $env:NODE_OPTIONS = "--title=dm-tech-port-$PORT --max-old-space-size=1024"
            Start-Process -NoNewWindow -FilePath "node.exe" -ArgumentList ".next-$PORT\standalone\$APP_DIR\server.js"
        } else {
            Write-Host "   (Standard Build - Launching via Next Start...)"
            $env:NEXT_DIST_DIR = ".next-$PORT"
            $env:PORT = $PORT
            $env:__NEXT_PRIVATE_PREBUNDLED_REACT = "1"
            $env:NEXT_PRIVATE_WORKER = "1"
            $env:NEXT_TELEMETRY_DISABLED = "1"
            $env:NODE_OPTIONS = "--title=dm-tech-port-$PORT --max-old-space-size=1024"
            Start-Process -NoNewWindow -FilePath "npx.cmd" -ArgumentList "next start -p $PORT"
        }
    }
}

# 2. Launch Frontends
Launch-Port -APP_DIR "frontend" -PORT "3000"
Launch-Port -APP_DIR "frontend" -PORT "3001"
Launch-Port -APP_DIR "frontend" -PORT "3002"
Launch-Port -APP_DIR "frontend" -PORT "3003"

# 3. Launch Guest-Apps
Launch-Port -APP_DIR "guest-app" -PORT "3004"
Launch-Port -APP_DIR "guest-app" -PORT "3005"

Write-Host "✅ 6-Port Native Isolation active." -ForegroundColor Green
Write-Host "Press Ctrl+C to exit and cleanup..."

try {
    while ($true) { Start-Sleep -Seconds 1 }
} finally {
    Write-Host "🧹 Teardown initiated. Stopping Node and PHP processes..." -ForegroundColor Yellow
    Stop-Process -Name "node" -ErrorAction SilentlyContinue
    Stop-Process -Name "php" -ErrorAction SilentlyContinue
    Remove-Item -Path "$env:TEMP\dmtech-isolation" -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "✅ Shutdown complete." -ForegroundColor Green
}

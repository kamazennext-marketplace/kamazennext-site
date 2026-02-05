# Deploy to Production Script (Git Push)
# Usage: ./deploy.ps1

Write-Host "=== Kama ZenNext Deployment (Push) ===" -ForegroundColor Cyan

# Configuration
$RemoteName = "production"
$RemoteUrl = "ssh://kamazennext@kamazennext.com/home2/kamazennext/repositories/kamazennext-site2"

# 1. Check if remote exists
$grid = git remote -v
if ($grid -match $RemoteName) {
    Write-Host "Remote '$RemoteName' already exists." -ForegroundColor Gray
}
else {
    Write-Host "Adding remote '$RemoteName'..." -ForegroundColor Yellow
    git remote add $RemoteName $RemoteUrl
}

# 2. Push to Remote
Write-Host "`nPushing changes to production..." -ForegroundColor Yellow
Write-Host "Target: $RemoteUrl" -ForegroundColor Gray
Write-Host "You may be asked to type 'yes' to accept the host key, and then your password." -ForegroundColor Magenta
Write-Host "---------------------------------------------------" -ForegroundColor DarkGray

git push $RemoteName main

if ($LASTEXITCODE -eq 0) {
    Write-Host "`nSUCCESS: Changes pushed to production repository." -ForegroundColor Green
    Write-Host "Note: Verification on the live site may take a few moments if there is a deployment hook." -ForegroundColor Gray
}
else {
    Write-Host "`nFAILURE: Git push failed." -ForegroundColor Red
}

Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

param(
    [string]$Branch = ""
)

$ErrorActionPreference = "Stop"

function Write-Banner($text) {
    $line = "=" * 50
    Write-Host "`n$line" -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor White
    Write-Host "$line`n" -ForegroundColor Cyan
}

function Get-NextVersion([string]$lastTag) {
    if (-not $lastTag -or $lastTag -match "fatal") {
        return "v1.0.0"
    }

    $version = $lastTag -replace '^v', ''
    $parts = $version -split '\.'

    $major = [int]$parts[0]
    $minor = if ($parts.Count -gt 1) { [int]$parts[1] } else { 0 }
    $patch = if ($parts.Count -gt 2) { [int]$parts[2] } else { 0 }

    $patch++
    return "v$major.$minor.$patch"
}

# -- Detect current branch ----------------------
if ([string]::IsNullOrWhiteSpace($Branch)) {
    $Branch = git branch --show-current 2>$null
    if ([string]::IsNullOrWhiteSpace($Branch)) {
        Write-Host "  Could not detect current branch. Use -Branch to specify one." -ForegroundColor Red
        exit 1
    }
}

# -- Validate remote -----------------------------
$remote = git remote 2>$null
if ([string]::IsNullOrWhiteSpace($remote)) {
    Write-Host "  No git remote configured. Add one with:" -ForegroundColor Red
    Write-Host "    git remote add origin <url>" -ForegroundColor Yellow
    exit 1
}

# -- Header ---------------------------------------
Write-Banner "Content Publisher Release"

# -- Detect last tag --------------------------------
$lastTag = $null
try { $lastTag = (git describe --tags --abbrev=0 2>&1) | Where-Object { $_ -notmatch 'fatal' } | Select-Object -First 1 } catch {}
$nextTag = Get-NextVersion $lastTag

Write-Host "  Branch:        " -NoNewline; Write-Host $Branch -ForegroundColor Cyan
if ($lastTag) {
    Write-Host "  Last tag:      " -NoNewline; Write-Host $lastTag -ForegroundColor Yellow
} else {
    Write-Host "  No previous tags found." -ForegroundColor DarkGray
}
Write-Host "  Suggested next:" -NoNewline; Write-Host " $nextTag" -ForegroundColor Green
Write-Host ""

# -- Stage 1: Commit --------------------------------
Write-Banner "Stage 1: Commit Changes"

$status = git status --short
if (-not $status) {
    Write-Host "  Working tree clean -- nothing to commit." -ForegroundColor DarkGray
    $skipCommit = $true
} else {
    Write-Host "  Changed files:" -ForegroundColor DarkGray
    $status | ForEach-Object { Write-Host "    $_" -ForegroundColor Gray }
    Write-Host ""

    $commitMsg = Read-Host "  Commit message"
    if ([string]::IsNullOrWhiteSpace($commitMsg)) {
        Write-Host "  Aborted: commit message cannot be empty." -ForegroundColor Red
        exit 1
    }

    git add .
    git commit -m $commitMsg
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  Commit failed." -ForegroundColor Red
        exit 1
    }
    Write-Host "  Committed." -ForegroundColor Green
}

# -- Stage 2: Tag -----------------------------------
Write-Banner "Stage 2: Create Tag"

$inputTag = Read-Host "  Tag version [$nextTag]"
if ([string]::IsNullOrWhiteSpace($inputTag)) {
    $inputTag = $nextTag
}
if ($inputTag -notmatch '^v') {
    $inputTag = "v$inputTag"
}

# Validate semver-ish format
if ($inputTag -notmatch '^v\d+\.\d+\.\d+$') {
    Write-Host "  Warning: '$inputTag' doesn't look like semver (vX.Y.Z). Continue? [y/N] " -ForegroundColor Yellow -NoNewline
    $confirm = Read-Host
    if ($confirm -ne 'y') {
        Write-Host "  Aborted." -ForegroundColor Red
        exit 1
    }
}

# Check tag doesn't already exist
$existingTags = git tag --list $inputTag
if ($existingTags) {
    Write-Host "  Tag '$inputTag' already exists!" -ForegroundColor Red
    exit 1
}

git tag $inputTag
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Failed to create tag." -ForegroundColor Red
    exit 1
}
Write-Host "  Tag created: $inputTag" -ForegroundColor Green

# -- Stage 3: Push ----------------------------------
Write-Banner "Stage 3: Push to Remote"

Write-Host "  Pushing commits to $Branch..." -ForegroundColor DarkGray
git push origin $Branch
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Failed to push commits." -ForegroundColor Red
    exit 1
}

Write-Host "  Pushing tag $inputTag..." -ForegroundColor DarkGray
git push origin $inputTag
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Failed to push tag." -ForegroundColor Red
    exit 1
}

# -- Done -------------------------------------------
Write-Banner "Release Complete!"

Write-Host "  Tag:     $inputTag" -ForegroundColor Green
Write-Host "  Branch:  $Branch" -ForegroundColor Green
Write-Host ""
Write-Host "  Packagist will auto-update shortly." -ForegroundColor DarkGray
Write-Host ""

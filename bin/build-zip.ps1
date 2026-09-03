# Build a clean WordPress.org-ready zip (and optional Plugin Check staging folder).
#
# Usage:
#   .\bin\build-zip.ps1
#   .\bin\build-zip.ps1 -StageForPluginCheck
#
# Output:
#   ..\tapio-clean-backend-1.0.0.zip
#   ..\tapio-clean-backend-dist\   (only with -StageForPluginCheck; leave inactive)

param(
	[switch]$StageForPluginCheck
)

$ErrorActionPreference = 'Stop'

$pluginRoot = Split-Path -Parent $PSScriptRoot
$pluginSlug = 'tapio-clean-backend'
$distIgnorePath = Join-Path $pluginRoot '.distignore'
$mainFile = Join-Path $pluginRoot 'tapio-clean-backend.php'

if ( -not ( Test-Path $mainFile ) ) {
	throw "Main plugin file not found: $mainFile"
}

$versionMatch = Select-String -Path $mainFile -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1
if ( -not $versionMatch ) {
	throw 'Could not read Version header from tapio-clean-backend.php'
}
$version = $versionMatch.Matches[0].Groups[1].Value.Trim()

$ignorePatterns = @()
if ( Test-Path $distIgnorePath ) {
	$ignorePatterns = Get-Content $distIgnorePath |
		ForEach-Object { $_.Trim() } |
		Where-Object { $_ -ne '' -and -not $_.StartsWith('#') }
}

function Test-IgnoredPath {
	param(
		[string]$RelativePath
	)

	$normalized = $RelativePath.Replace('\', '/')
	foreach ( $pattern in $ignorePatterns ) {
		$p = $pattern.Replace('\', '/').TrimStart('/')
		if ( $normalized -eq $p ) {
			return $true
		}
		if ( $p.EndsWith('/') -and $normalized.StartsWith( $p ) ) {
			return $true
		}
		if ( $normalized -like $p ) {
			return $true
		}
		# Directory-style match: "bin" ignores bin/**
		if ( $normalized -eq $p -or $normalized.StartsWith( "$p/" ) ) {
			return $true
		}
	}
	return $false
}

$parentDir = Split-Path -Parent $pluginRoot
$stageRoot = Join-Path $env:TEMP ("$pluginSlug-build-" + [guid]::NewGuid().ToString('N'))
$stagePlugin = Join-Path $stageRoot $pluginSlug

New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null

Get-ChildItem -Path $pluginRoot -Recurse -Force -File | ForEach-Object {
	$relative = $_.FullName.Substring( $pluginRoot.Length ).TrimStart( '\', '/' )
	if ( Test-IgnoredPath -RelativePath $relative ) {
		return
	}

	# Also skip anything under an ignored directory prefix.
	$parts = $relative.Replace('\', '/').Split('/')
	$accum = ''
	foreach ( $part in $parts[0..([Math]::Max(0, $parts.Length - 2))] ) {
		if ( $part -eq '' ) { continue }
		$accum = if ( $accum -eq '' ) { $part } else { "$accum/$part" }
		if ( Test-IgnoredPath -RelativePath $accum ) {
			return
		}
	}

	$dest = Join-Path $stagePlugin $relative
	$destDir = Split-Path -Parent $dest
	if ( -not ( Test-Path $destDir ) ) {
		New-Item -ItemType Directory -Path $destDir -Force | Out-Null
	}
	Copy-Item -LiteralPath $_.FullName -Destination $dest -Force
}

$zipPath = Join-Path $parentDir "$pluginSlug-$version.zip"
if ( Test-Path $zipPath ) {
	Remove-Item -LiteralPath $zipPath -Force
}

Compress-Archive -Path $stagePlugin -DestinationPath $zipPath -Force

if ( $StageForPluginCheck ) {
	$distDir = Join-Path $parentDir 'tapio-clean-backend-dist'
	if ( Test-Path $distDir ) {
		Remove-Item -LiteralPath $distDir -Recurse -Force
	}
	Copy-Item -LiteralPath $stagePlugin -Destination $distDir -Recurse -Force
	Write-Host "Staged inactive Plugin Check copy at: $distDir"
}

Remove-Item -LiteralPath $stageRoot -Recurse -Force

Write-Host "Built: $zipPath"

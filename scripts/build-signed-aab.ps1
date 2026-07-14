param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path $PSScriptRoot -Parent
Push-Location $repoRoot

try {
    $javaHome = Join-Path $HOME '.bubblewrap\jdk\jdk-17.0.11+9'
    if (Test-Path (Join-Path $javaHome 'bin\java.exe')) {
        $env:JAVA_HOME = $javaHome
        $env:Path = (Join-Path $javaHome 'bin') + ';' + $env:Path
    }

    $sdkPath = Join-Path $HOME '.bubblewrap\android_sdk'
    if (-not (Test-Path $sdkPath)) {
        throw 'Android SDK not found at ~/.bubblewrap/android_sdk.'
    }

    $escapedSdkPath = $sdkPath.Replace('\', '\\')
    Set-Content -Path (Join-Path $repoRoot 'local.properties') -Value "sdk.dir=$escapedSdkPath" -Encoding ASCII

    $tempPhp = Join-Path $env:TEMP ('read_twa_settings_' + $PID + '.php')
    @'
<?php
$db = new PDO('sqlite:database/database.sqlite');
$stmt = $db->query("SELECT key, value FROM app_settings WHERE key IN ('twa_keystore_password','twa_key_password','twa_signing_key_alias','twa_keystore_store_type')");
echo json_encode($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
'@ | Set-Content -Path $tempPhp -Encoding ASCII

    try {
        $settingsJson = & php $tempPhp
    }
    finally {
        if (Test-Path $tempPhp) {
            Remove-Item $tempPhp -Force
        }
    }

    $settings = $settingsJson | ConvertFrom-Json
    $alias = [string]$settings.twa_signing_key_alias
    if ([string]::IsNullOrWhiteSpace($alias)) { $alias = 'android' }
    $storePass = [string]$settings.twa_keystore_password
    $keyPass = [string]$settings.twa_key_password
    $storeType = [string]$settings.twa_keystore_store_type
    if ([string]::IsNullOrWhiteSpace($storeType)) { $storeType = 'PKCS12' }

    $keystoreCandidates = @(
        (Join-Path $repoRoot 'storage\app\twa\generated\android-release.p12'),
        (Join-Path $repoRoot 'storage\app\twa\generated\android-release.jks'),
        (Join-Path $repoRoot 'android.p12'),
        (Join-Path $repoRoot 'android.jks')
    )

    $storeFile = $null
    foreach ($candidate in $keystoreCandidates) {
        if (Test-Path $candidate) {
            $storeFile = (Resolve-Path $candidate).Path
            break
        }
    }

    if ($null -eq $storeFile) {
        throw 'No signing keystore file found.'
    }

    & .\gradlew.bat 'bundleRelease' '--no-daemon' "-Pandroid.injected.signing.store.file=$storeFile" "-Pandroid.injected.signing.store.password=$storePass" "-Pandroid.injected.signing.store.type=$storeType" "-Pandroid.injected.signing.key.alias=$alias" "-Pandroid.injected.signing.key.password=$keyPass"
    if ($LASTEXITCODE -ne 0) {
        throw 'Gradle bundleRelease failed.'
    }

    $aab = Join-Path $repoRoot 'app\build\outputs\bundle\release\app-release.aab'
    if (-not (Test-Path $aab)) {
        throw 'AAB not found after build.'
    }

    $artifact = Get-Item $aab
    Write-Host "AAB generated: $($artifact.FullName)"
    Write-Host "Size: $($artifact.Length) bytes"
    Write-Host "Modified: $($artifact.LastWriteTime.ToString('yyyy-MM-dd HH:mm:ss'))"
}
finally {
    Pop-Location
}

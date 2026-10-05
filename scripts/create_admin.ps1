param(
    [string]$Email = 'admin@chalk.local',
    [string]$Php = 'C:\xampppp\php\php.exe',
    [Security.SecureString]$Password
)
$ErrorActionPreference = 'Stop'
if (!$Password) { $Password = Read-Host 'Choose an admin password (at least 12 characters)' -AsSecureString }
$previousEmail = $env:QUIZWEB_ADMIN_EMAIL
$previousPassword = $env:QUIZWEB_ADMIN_PASSWORD
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Password)
try {
    $env:QUIZWEB_ADMIN_EMAIL = $Email
    $env:QUIZWEB_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    & $Php -d extension=pdo_pgsql (Join-Path $PSScriptRoot 'create_admin.php')
    if ($LASTEXITCODE -ne 0) { throw 'Admin account was not created. Check the database environment variables and PostgreSQL service.' }
    Write-Output 'Open http://localhost/QuizWeb/login.php and sign in with the email and password you chose.'
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $env:QUIZWEB_ADMIN_EMAIL = $previousEmail
    $env:QUIZWEB_ADMIN_PASSWORD = $previousPassword
}

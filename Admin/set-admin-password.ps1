$ErrorActionPreference = 'Stop'
$adminEmail = (Read-Host 'Admin email stored in trendy_store.admin_users').Trim()
if ($adminEmail -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$') {
    throw 'Enter a valid admin email address.'
}
$securePassword = Read-Host 'Choose a new admin password (12+ characters)' -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    if ($plainPassword.Length -lt 12) {
        throw 'Use a password with at least 12 characters.'
    }

    $env:TRENDY_ADMIN_RESET_PASSWORD = $plainPassword
    $phpCode = @'
$password = getenv('TRENDY_ADMIN_RESET_PASSWORD');
$adminEmail = getenv('TRENDY_ADMIN_RESET_EMAIL');
$host = getenv('TRENDY_DB_HOST') ?: '127.0.0.1';
$database = getenv('TRENDY_DB_NAME') ?: 'trendy_store';
$username = getenv('TRENDY_DB_USER') ?: 'krish1@gmail.com';
$dbPassword = getenv('TRENDY_DB_PASS') ?: 'Krish@123';
$pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $pdo->prepare('UPDATE admin_users SET password = :password WHERE email = :email');
$statement->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'email' => $adminEmail]);
if ($statement->rowCount() !== 1) { fwrite(STDERR, "Admin account not found or unchanged.\n"); exit(1); }
echo "Admin password updated for {$adminEmail}.\n";
'@
    $env:TRENDY_ADMIN_RESET_EMAIL = $adminEmail
    & 'C:\xampp\php\php.exe' -r $phpCode
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not update the admin password. Confirm XAMPP MariaDB is running and trendy_store is imported.'
    }
}
finally {
    Remove-Item Env:TRENDY_ADMIN_RESET_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:TRENDY_ADMIN_RESET_EMAIL -ErrorAction SilentlyContinue
    if ($bstr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
    if ($securePassword) {
        $securePassword.Dispose()
    }
    $plainPassword = $null
}


# ============================================================
# StyleHub Authenticated API Performance Test
# ============================================================
#
# Run from the Laravel project directory:
#
# powershell -ExecutionPolicy Bypass -File .\scripts\run-authenticated-benchmark.ps1
#
# Uses existing StyleHub login endpoints.
# Prompts for passwords securely.
# Does not print access tokens or passwords.
# Sends only GET requests during benchmarking.
# Revokes temporary login tokens afterward.
#
# ============================================================

$ErrorActionPreference = 'Stop'

$baseUrl = 'http://127.0.0.1:8000'

$benchmarkFile = Join-Path `
    $PSScriptRoot `
    'benchmark-api.mjs'

$roles = @(
    @{
        Name = 'Customer'
        Code = 'customer'
        Login = '/api/v1/auth/customers/login'
        Logout = '/api/v1/auth/logout'
        Env = 'STYLEHUB_CUSTOMER_TOKENS'
    },
    @{
        Name = 'Shop Owner'
        Code = 'shop-owner'
        Login = '/api/v1/auth/shop-owners/login'
        Logout = '/api/v1/auth/logout'
        Env = 'STYLEHUB_OWNER_TOKENS'
    },
    @{
        Name = 'Administrator'
        Code = 'admin'
        Login = '/api/v1/admin/auth/login'
        Logout = '/api/v1/admin/auth/logout'
        Env = 'STYLEHUB_ADMIN_TOKENS'
    }
)

$sessions = @()
$originalEnvironment = @{}

$environmentNames = @(
    'STYLEHUB_BENCH_URL'
    'STYLEHUB_CUSTOMER_TOKENS'
    'STYLEHUB_OWNER_TOKENS'
    'STYLEHUB_ADMIN_TOKENS'
)

Write-Host ''
Write-Host '=========================================='
Write-Host ' STYLEHUB AUTHENTICATED API PERFORMANCE'
Write-Host '=========================================='
Write-Host ''

# ------------------------------------------------------------
# Check Node.js and existing benchmark
# ------------------------------------------------------------

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    Write-Host 'Node.js is not installed or unavailable.'
    exit 1
}

if (-not (Test-Path $benchmarkFile)) {
    Write-Host 'Missing scripts/benchmark-api.mjs'
    Write-Host 'Complete the previous benchmark step first.'
    exit 1
}

# ------------------------------------------------------------
# Check Laravel server
# ------------------------------------------------------------

Write-Host 'Checking Laravel connection...'

try {
    $health = Invoke-WebRequest `
        -Uri "$baseUrl/up" `
        -UseBasicParsing `
        -TimeoutSec 10

    if ($health.StatusCode -ne 200) {
        throw 'Laravel health check failed.'
    }

    Write-Host 'Laravel is reachable.'
}
catch {
    Write-Host 'Cannot connect to Laravel.'
    Write-Host 'Start Laravel on 127.0.0.1:8000.'
    exit 1
}

Write-Host ''
Write-Host 'Use dedicated local TEST accounts.'
Write-Host 'Press Enter to skip a role.'
Write-Host 'Passwords and tokens will not be printed.'
Write-Host ''

try {

    # --------------------------------------------------------
    # Save existing environment values
    # --------------------------------------------------------

    foreach ($name in $environmentNames) {
        $originalEnvironment[$name] = `
            [Environment]::GetEnvironmentVariable(
                $name,
                'Process'
            )

        [Environment]::SetEnvironmentVariable(
            $name,
            $null,
            'Process'
        )
    }

    [Environment]::SetEnvironmentVariable(
        'STYLEHUB_BENCH_URL',
        $baseUrl,
        'Process'
    )

    # --------------------------------------------------------
    # Authenticate test accounts
    # --------------------------------------------------------

    foreach ($role in $roles) {

        Write-Host ''
        Write-Host "ROLE: $($role.Name)"

        $email = Read-Host `
            'Enter test account email (Enter to skip)'

        if ([string]::IsNullOrWhiteSpace($email)) {
            Write-Host 'Skipped.'
            continue
        }

        $securePassword = Read-Host `
            'Enter password' `
            -AsSecureString

        $passwordPointer = [IntPtr]::Zero
        $password = $null
        $requestBody = $null
        $response = $null

        try {

            # Convert password only for the HTTP request.
            $passwordPointer = `
                [Runtime.InteropServices.Marshal]::SecureStringToBSTR(
                    $securePassword
                )

            $password = `
                [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
                    $passwordPointer
                )

            $requestBody = @{
                email = $email.Trim()
                password = $password
                device_name = "stylehub-benchmark-$($role.Code)"
            } | ConvertTo-Json -Compress

            $response = Invoke-RestMethod `
                -Uri "$baseUrl$($role.Login)" `
                -Method Post `
                -ContentType 'application/json' `
                -Headers @{
                    Accept = 'application/json'
                } `
                -Body $requestBody `
                -TimeoutSec 15

        }
        catch {
            Write-Warning "$($role.Name) login failed. Check test credentials."
            continue
        }
        finally {

            if ($passwordPointer -ne [IntPtr]::Zero) {
                [Runtime.InteropServices.Marshal]::ZeroFreeBSTR(
                    $passwordPointer
                )
            }

            $password = $null
            $requestBody = $null
            $securePassword = $null
        }

        if (
            $null -eq $response -or
            $null -eq $response.data -or
            [string]::IsNullOrWhiteSpace(
                [string]$response.data.token
            )
        ) {
            Write-Warning 'Login returned no usable access token.'
            continue
        }

        $token = [string]$response.data.token

        [Environment]::SetEnvironmentVariable(
            $role.Env,
            $token,
            'Process'
        )

        $sessions += [PSCustomObject]@{
            Name = $role.Name
            Token = $token
            Logout = $role.Logout
        }

        Write-Host "$($role.Name) authenticated successfully."

        $token = $null
    }

    # --------------------------------------------------------
    # Run existing Node.js benchmark
    # --------------------------------------------------------

    Write-Host ''
    Write-Host '=========================================='
    Write-Host ' BEGINNING PERFORMANCE MEASUREMENTS'
    Write-Host '=========================================='
    Write-Host ''

    if ($sessions.Count -eq 0) {

        Write-Warning 'No test accounts were authenticated.'
        Write-Host 'No authenticated benchmark was performed.'

    }
    else {

        Write-Host "Authenticated roles: $($sessions.Count)"
        Write-Host 'Testing 1, 3, and 5 concurrent GET requests.'
        Write-Host ''

        & node $benchmarkFile

        $benchmarkExitCode = $LASTEXITCODE

        Write-Host ''

        if ($benchmarkExitCode -eq 0) {
            Write-Host 'Benchmark execution completed successfully.'
        }
        else {
            Write-Warning 'Some benchmark requests failed.'
            Write-Host 'Review HTTP statuses in the output.'
        }
    }
}
finally {

    # --------------------------------------------------------
    # Revoke only temporary benchmark tokens
    # --------------------------------------------------------

    Write-Host ''
    Write-Host 'Cleaning up temporary login sessions...'

    foreach ($session in $sessions) {

        try {

            Invoke-RestMethod `
                -Uri "$baseUrl$($session.Logout)" `
                -Method Post `
                -Headers @{
                    Accept = 'application/json'
                    Authorization = "Bearer $($session.Token)"
                } `
                -TimeoutSec 15 |
                Out-Null

            Write-Host "$($session.Name) benchmark session revoked."

        }
        catch {
            Write-Warning "$($session.Name) logout failed. Revoke its benchmark token manually."
        }
    }

    # --------------------------------------------------------
    # Restore environment variables
    # --------------------------------------------------------

    foreach ($name in $environmentNames) {

        [Environment]::SetEnvironmentVariable(
            $name,
            $originalEnvironment[$name],
            'Process'
        )
    }

    Write-Host ''
    Write-Host '=========================================='
    Write-Host ' STYLEHUB PERFORMANCE TEST FINISHED'
    Write-Host '=========================================='
    Write-Host ''
}

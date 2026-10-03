param(
    [Parameter(Mandatory = $true)][string]$Url,
    [int]$Width = 1440,
    [int]$Height = 1000
)
$ErrorActionPreference = 'Stop'
$browserPath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
$profilePath = Join-Path ([IO.Path]::GetTempPath()) ('quizweb-browser-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $profilePath | Out-Null
$browserProcess = Start-Process -FilePath $browserPath -ArgumentList @('--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', "--user-data-dir=`"$profilePath`"", 'about:blank') -WindowStyle Hidden -PassThru
$socket = New-Object System.Net.WebSockets.ClientWebSocket
$script:messageId = 0
function Send-BrowserCommand([string]$Method, [hashtable]$Parameters = @{}) {
    $script:messageId++
    $id = $script:messageId
    $json = @{id=$id; method=$Method; params=$Parameters} | ConvertTo-Json -Depth 12 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
    do {
        $buffer = New-Object byte[] 65536
        $message = New-Object IO.MemoryStream
        do {
            $read = $socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
            $message.Write($buffer, 0, $read.Count)
        } until ($read.EndOfMessage)
        $reply = [Text.Encoding]::UTF8.GetString($message.ToArray()) | ConvertFrom-Json
        $message.Dispose()
    } until ($reply.id -eq $id)
    if ($reply.error) { throw ($reply.error | ConvertTo-Json -Compress) }
    return $reply.result
}
try {
    $portFile = Join-Path $profilePath 'DevToolsActivePort'
    for ($i = 0; $i -lt 50 -and !(Test-Path -LiteralPath $portFile); $i++) { Start-Sleep -Milliseconds 100 }
    $port = (Get-Content -LiteralPath $portFile)[0]
    $targets = Invoke-RestMethod "http://127.0.0.1:$port/json/list"
    $target = $targets | Where-Object type -eq 'page' | Select-Object -First 1
    if (!$target.webSocketDebuggerUrl) { throw 'Chrome did not provide a page debugging endpoint.' }
    $socket.ConnectAsync([Uri]$target.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
    Send-BrowserCommand 'Emulation.setDeviceMetricsOverride' @{width=$Width; height=$Height; deviceScaleFactor=1; mobile=($Width -lt 761)} | Out-Null
    Send-BrowserCommand 'Page.navigate' @{url=$Url} | Out-Null
    $result = $null
    for ($i = 0; $i -lt 80; $i++) {
        Start-Sleep -Milliseconds 100
        $result = Send-BrowserCommand 'Runtime.evaluate' @{expression='JSON.stringify({check:document.getElementById("checks")?.textContent,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth})'; returnByValue=$true}
        if ($result.result.value) {
            $state = $result.result.value | ConvertFrom-Json
            if ($state.check -match '^(PASS|FAIL)') { break }
        }
    }
    if (!$state -or $state.check -notmatch '^PASS' -or $state.width -ne $Width -or $state.overflow) { throw "Browser check failed: $($result.result.value)" }
    Write-Output "$Width px: $($state.check)"
    Send-BrowserCommand 'Browser.close' | Out-Null
} finally {
    $socket.Dispose()
    if (!$browserProcess.HasExited) { $browserProcess.WaitForExit(3000) | Out-Null }
    # Only this invocation's disposable browser process and profile may be removed.
    if (!$browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id }
    $resolvedProfile = [IO.Path]::GetFullPath($profilePath)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
    if ($resolvedProfile.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and (Split-Path $resolvedProfile -Leaf) -match '^quizweb-browser-[0-9a-f]{32}$') {
        Remove-Item -LiteralPath $resolvedProfile -Recurse -Force
    }
}

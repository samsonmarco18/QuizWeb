param(
    [Parameter(Mandatory = $true)][string]$Url,
    [int]$Width = 1440,
    [int]$Height = 1000,
    [switch]$Matrix,
    [switch]$Security,
    [ValidateSet('reduce','no-preference')][string]$Motion = 'reduce'
)
$ErrorActionPreference = 'Stop'
$browserPath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
$profilePath = Join-Path ([IO.Path]::GetTempPath()) ('quizweb-browser-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $profilePath | Out-Null
$browserProcess = Start-Process -FilePath $browserPath -ArgumentList @('--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', "--user-data-dir=`"$profilePath`"", 'about:blank') -WindowStyle Hidden -PassThru
$socket = New-Object System.Net.WebSockets.ClientWebSocket
$socket.Options.KeepAliveInterval = [TimeSpan]::FromSeconds(10)
$script:messageId = 0
$script:browserErrors = @()
function Send-BrowserCommand([string]$Method, [hashtable]$Parameters = @{}) {
    $script:messageId++
    $id = $script:messageId
    $json = @{id=$id; method=$Method; params=$Parameters} | ConvertTo-Json -Depth 12 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
    do {
        $buffer = New-Object byte[] 65536
        $message = New-Object IO.MemoryStream
        do {
            $read = $socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
            $message.Write($buffer, 0, $read.Count)
        } until ($read.EndOfMessage)
        $reply = [Text.Encoding]::UTF8.GetString($message.ToArray()) | ConvertFrom-Json
        if ($reply.method -eq 'Log.entryAdded') { $script:browserErrors += $reply.params.entry.text }
        if ($reply.method -eq 'Runtime.exceptionThrown') { $script:browserErrors += ($reply.params.exceptionDetails | ConvertTo-Json -Depth 8 -Compress) }
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
    Send-BrowserCommand 'Runtime.enable' | Out-Null
    Send-BrowserCommand 'Log.enable' | Out-Null
    $cases = @(@{width=$Width; height=$Height; url=$Url; name='single'})
    if ($Matrix) {
        $cases = @()
        foreach ($size in @(@(360,800),@(390,844),@(430,932),@(768,1024),@(1024,768),@(1366,768),@(1440,900))) {
            foreach ($theme in @('light','dark')) {
                foreach ($view in @('picker','builder','preview','standard','time_attack','rocket_rush','memory_flip','treasure_dive','boss_battle','master_ladder','crossword','flip_match','fill_blank','emoji_quiz','results')) {
                    $cases += @{width=$size[0];height=$size[1];url=($Url+'?view='+$view+'&theme='+$theme+'&feedback=1');name=($view+'-'+$theme+'-'+$size[0])}
                }
                foreach ($step in @(1,3,4,5)) {
                    $cases += @{width=$size[0];height=$size[1];url=($Url+'?view=builder&step='+$step+'&theme='+$theme);name=('builder-step'+$step+'-'+$theme+'-'+$size[0])}
                }
            }
        }
    }
    $report = @(); $failed = @()
    $screenshotDir = Join-Path (Get-Location) 'data/qa/screenshots'
    if ($Matrix) { New-Item -ItemType Directory -Path $screenshotDir -Force | Out-Null }
    foreach ($case in $cases) {
        Send-BrowserCommand 'Emulation.setDeviceMetricsOverride' @{width=$case.width; height=$case.height; deviceScaleFactor=1; mobile=($case.width -lt 761)} | Out-Null
        Send-BrowserCommand 'Emulation.setEmulatedMedia' @{features=@(@{name='prefers-reduced-motion';value=$Motion})} | Out-Null
        Send-BrowserCommand 'Page.navigate' @{url=$case.url} | Out-Null
        $state = $null
        for ($i = 0; $i -lt 80; $i++) {
            Start-Sleep -Milliseconds 100
            $result = Send-BrowserCommand 'Runtime.evaluate' @{expression='JSON.stringify({check:document.getElementById("checks")?.textContent,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+2})'; returnByValue=$true}
            if ($result.result.value) {
                $state = $result.result.value | ConvertFrom-Json
                if ($Security -and $state.check -eq 'READY') {
                    Send-BrowserCommand 'Runtime.evaluate' @{expression='window.runSecurityBrowserStep()'; awaitPromise=$true; userGesture=$true; returnByValue=$true} | Out-Null
                }
                if ($state.check -match '^(PASS|FAIL)') { break }
            }
        }
        $passed = $state -and $state.check -match '^PASS' -and $state.width -eq $case.width -and !$state.overflow
        $report += @{name=$case.name;passed=[bool]$passed;state=$state}
        if (!$passed) { $failed += $case.name; Write-Output ("FAIL "+$case.name+' '+($state|ConvertTo-Json -Compress)) }
        if ($Matrix) {
            $shot = Send-BrowserCommand 'Page.captureScreenshot' @{format='png';captureBeyondViewport=$false}
            [IO.File]::WriteAllBytes((Join-Path $screenshotDir ($case.name+'-'+$Motion+'.png')), [Convert]::FromBase64String($shot.data))
        }
    }
    if ($Matrix) { $report | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath ('data/qa/responsive-'+$Motion+'-report.json') -Encoding UTF8 }
    Write-Output ("Browser cases: "+($cases.Count-$failed.Count)+' / '+$cases.Count+' passed')
    if ($failed.Count) { Write-Output ($script:browserErrors | Select-Object -Unique) }
    if ($failed.Count) { throw ('Failed views: '+($failed -join ', ')) }
    Send-BrowserCommand 'Browser.close' | Out-Null
} finally {
    $socket.Dispose()
    if (!$browserProcess.HasExited) { $browserProcess.WaitForExit(3000) | Out-Null }
    # Only this invocation's disposable browser process and profile may be removed.
    if (!$browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id }
    $resolvedProfile = [IO.Path]::GetFullPath($profilePath)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
    if ($resolvedProfile.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and (Split-Path $resolvedProfile -Leaf) -match '^quizweb-browser-[0-9a-f]{32}$') {
        try { Remove-Item -LiteralPath $resolvedProfile -Recurse -Force -ErrorAction Stop } catch { Write-Warning 'Chrome is still releasing its disposable profile. Browser results are preserved.' }
    }
}

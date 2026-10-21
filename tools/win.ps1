# Window automation helpers used to capture genuine desktop screenshots
# (Azure portal in the signed-in browser, Visual Studio Code, terminals).
# Dot-source this file:  . .\tools\win.ps1

Add-Type -AssemblyName System.Windows.Forms, System.Drawing

if (-not ('Win32' -as [type])) {
    Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Runtime.InteropServices;
public class Win32 {
    public delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);
    [DllImport("user32.dll")] public static extern bool EnumWindows(EnumWindowsProc cb, IntPtr p);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowText(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr h);
    [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr h, int cmd);
    [DllImport("user32.dll")] public static extern bool SetWindowPos(IntPtr h, IntPtr after, int x, int y, int cx, int cy, uint flags);
    [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll")] public static extern bool BringWindowToTop(IntPtr h);
    [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h, out uint pid);
    public struct RECT { public int Left, Top, Right, Bottom; }
}
'@
}

function Get-AppWindow {
    param([Parameter(Mandatory)][string]$TitleLike, [switch]$Exact)
    $found = New-Object System.Collections.ArrayList
    $cb = [Win32+EnumWindowsProc] {
        param($h, $l)
        if ([Win32]::IsWindowVisible($h)) {
            $sb = New-Object System.Text.StringBuilder 512
            [void][Win32]::GetWindowText($h, $sb, 512)
            $t = $sb.ToString()
            if ($t) {
                if (($Exact -and $t -eq $TitleLike) -or (-not $Exact -and $t -like $TitleLike)) {
                    $r = New-Object Win32+RECT
                    [void][Win32]::GetWindowRect($h, [ref]$r)
                    [void]$found.Add([pscustomobject]@{
                        Hwnd  = $h
                        Title = $t
                        X     = $r.Left
                        Y     = $r.Top
                        Width = $r.Right - $r.Left
                        Height= $r.Bottom - $r.Top
                    })
                }
            }
        }
        return $true
    }
    [void][Win32]::EnumWindows($cb, [IntPtr]::Zero)
    return $found
}

function Set-WindowRect {
    param([IntPtr]$Hwnd, [int]$X, [int]$Y, [int]$Width, [int]$Height)
    [void][Win32]::ShowWindow($Hwnd, 9)            # SW_RESTORE
    [void][Win32]::SetWindowPos($Hwnd, [IntPtr]::Zero, $X, $Y, $Width, $Height, 0x0040)
    [void][Win32]::SetForegroundWindow($Hwnd)
    Start-Sleep -Milliseconds 700
}

function Set-WindowMaximized {
    param([IntPtr]$Hwnd)
    [void][Win32]::ShowWindow($Hwnd, 3)            # SW_MAXIMIZE
    [void][Win32]::BringWindowToTop($Hwnd)
    [void][Win32]::SetForegroundWindow($Hwnd)
    Start-Sleep -Milliseconds 900
}

function Focus-Window {
    param([Parameter(Mandatory)][IntPtr]$Hwnd)
    [void][Win32]::ShowWindow($Hwnd, 3)
    [void][Win32]::SetWindowPos($Hwnd, [IntPtr](-1), 0, 0, 0, 0, 0x0003)   # HWND_TOPMOST
    [void][Win32]::BringWindowToTop($Hwnd)
    [void][Win32]::SetForegroundWindow($Hwnd)
    Start-Sleep -Milliseconds 800
    [void][Win32]::SetWindowPos($Hwnd, [IntPtr](-2), 0, 0, 0, 0, 0x0003)   # HWND_NOTOPMOST
    Start-Sleep -Milliseconds 400
}

function Send-Keys {
    param([string]$Keys, [int]$WaitMs = 300)
    $ws = New-Object -ComObject WScript.Shell
    $ws.SendKeys($Keys)
    Start-Sleep -Milliseconds $WaitMs
}

function Save-WindowShot {
    param(
        [Parameter(Mandatory)][string]$Path,
        [IntPtr]$Hwnd = [IntPtr]::Zero,
        [int]$Pad = 0
    )
    if ($Hwnd -eq [IntPtr]::Zero) { $Hwnd = [Win32]::GetForegroundWindow() }
    $r = New-Object Win32+RECT
    [void][Win32]::GetWindowRect($Hwnd, [ref]$r)
    $x = [Math]::Max(0, $r.Left - $Pad)
    $y = [Math]::Max(0, $r.Top - $Pad)
    $w = [Math]::Min(([System.Windows.Forms.SystemInformation]::VirtualScreen.Width - $x), ($r.Right - $r.Left) + 2 * $Pad)
    $h = [Math]::Min(([System.Windows.Forms.SystemInformation]::VirtualScreen.Height - $y), ($r.Bottom - $r.Top) + 2 * $Pad)
    $bmp = New-Object System.Drawing.Bitmap $w, $h
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.CopyFromScreen($x, $y, 0, 0, $bmp.Size)
    $dir = Split-Path -Parent $Path
    if ($dir -and -not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose(); $bmp.Dispose()
    return [pscustomobject]@{ Path = $Path; Width = $w; Height = $h }
}

function Open-InBrowser {
    param(
        [Parameter(Mandatory)][string]$Url,
        [string]$TitleLike = '*Mozilla Firefox*',
        [int]$SettleSeconds = 10
    )
    $win = Get-AppWindow -TitleLike $TitleLike | Select-Object -First 1
    if (-not $win) { throw "No browser window matching $TitleLike" }
    Set-WindowMaximized $win.Hwnd
    Send-Keys '^l' 400
    Send-Keys '^a' 150
    $ws = New-Object -ComObject WScript.Shell
    $ws.SendKeys($Url)
    Start-Sleep -Milliseconds 400
    Send-Keys '{ENTER}' 500
    Start-Sleep -Seconds $SettleSeconds
    return $win.Hwnd
}

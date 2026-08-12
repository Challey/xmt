# XMT host access from Windows

## Recommended: `192.168.16.1` + portproxy

Windows owns the WSL Hyper-V gateway address **192.168.16.1** (`vEthernet (WSL ...)`). Put site names in the Windows `hosts` file pointing at that address, and forward Windows port 80 to nginx inside WSL.

### 1. Hosts file

Run as Administrator:

`\\wsl$\Ubuntu\home\wwwroot\xmt\setup\add-xmt-hosts.bat`

(or the copy under `/home/wwwroot/xmt/setup/add-xmt-hosts.bat`). Entries look like:

```
192.168.16.1 xmt.wsl zhubao.wsl ...
192.168.16.1 xmt.pub zhubao.pub ...
```

You do **not** need to change hosts when the WSL eth0 IP changes.

### 2. Portproxy (Windows :80 → WSL :80)

WSL nginx listens on the WSL eth0 IP (e.g. `192.168.29.30`), which can change after reboot. Portproxy must forward to the **current** WSL IP:

```
netsh interface portproxy add v4tov4 listenaddress=0.0.0.0 listenport=80 connectaddress=WSL_IP connectport=80
netsh interface portproxy add v4tov4 listenaddress=192.168.16.1 listenport=80 connectaddress=WSL_IP connectport=80
```

**IP Helper (`iphlpsvc`) must be running** — without it, portproxy rules exist but nothing listens and connections are refused.

Helper (Run as Administrator):

`C:\Users\Public\xmt-portproxy.bat`

It starts IP Helper, runs `wsl -e hostname -I`, updates portproxy, and adds a firewall allow for TCP 80.

**After every WSL reboot** (if eth0 IP changed), re-run `xmt-portproxy.bat` as Admin. Hosts can stay on `192.168.16.1`.

Check:

```
netsh interface portproxy show all
sc query iphlpsvc
```

### 3. Why not `127.0.0.1`?

By default, Windows and WSL do not share loopback. Traffic to `127.0.0.1` on Windows stays on Windows. Also, `wslrelay` may already bind `127.0.0.1:80` for other WSL forwarding; use `192.168.16.1` (and/or `0.0.0.0` portproxy) instead.

### Quick check current WSL IP

```bash
wsl hostname -I
# or inside WSL:
ip -4 -o addr show eth0 | awk '{print $4}' | cut -d/ -f1
```

### Hairpin note

`curl http://192.168.16.1/` from **inside WSL** may fail (hairpin to the gateway). Test from a Windows browser or `Invoke-WebRequest` on the host.

## Alternative: mirrored networking

In `%UserProfile%\.wslconfig`:

```ini
[wsl2]
networkingMode=mirrored
```

Restart WSL (`wsl --shutdown`, then reopen). With mirrored mode, `127.0.0.1` mappings in Windows hosts can work for services bound in WSL.

## ERR_EMPTY_RESPONSE / wrong IP (VPN fake-ip)

If `http://192.168.16.1/` shows the LNMP default page but `http://xmt.wsl/` fails with **ERR_EMPTY_RESPONSE**, check name resolution on Windows:

```
ping xmt.wsl
```

If it resolves to something like **198.18.x.x** (Clash/VPN fake-ip) instead of **192.168.16.1**, the browser never reaches WSL. LNMPA (nginx → Apache :88) is fine; add hosts entries:

```
\\wsl$\Ubuntu\home\wwwroot\xmt\setup\add-xmt-hosts.bat
```

(Run as Administrator.) Then `ping xmt.wsl` should show `192.168.16.1`.

Quick proof without hosts: `Invoke-WebRequest http://192.168.16.1/ -Headers @{Host='xmt.wsl'}` should return 200 Drupal HTML.


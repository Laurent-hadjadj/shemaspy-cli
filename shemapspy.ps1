#Dans votre profil PowerShell (profile.ps1)
function schemaspy {
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
    $env:TERM = 'xterm-256color'
    php src/bootstrap.php $args
}

# Utilisation
schemaspy --help

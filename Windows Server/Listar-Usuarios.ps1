# --- Configuracion del dominio ---
$DC           = "DC=usuariosbiblioteca,DC=local"
$OU_Provisional  = "OU=Usuarios_Provisionales,$DC"
$OU_Definitivos  = "OU=Usuarios_Definitivos,$DC"


# Obtiene todos los usuarios de las dos OUs con los campos necesarios
$Usuarios = Get-ADUser -Filter * -SearchBase $DC `
    -Properties SamAccountName, GivenName, Surname, EmailAddress, Enabled, whenCreated, DistinguishedName |
    Where-Object { $_.DistinguishedName -like "*Usuarios_Provisionales*" -or $_.DistinguishedName -like "*Usuarios_Definitivos*" } |
    Select-Object @(
        @{ Name = "samaccount";  Expression = { $_.SamAccountName } },
        @{ Name = "nombre";      Expression = { $_.GivenName } },
        @{ Name = "apellidos";   Expression = { $_.Surname } },
        @{ Name = "email";       Expression = { $_.EmailAddress } },
        @{ Name = "habilitado";  Expression = { $_.Enabled } },
        @{ Name = "ou";          Expression = { if ($_.DistinguishedName -like "*Usuarios_Provisionales*") { "Provisional" } else { "Definitivo" } } },
        @{ Name = "fecha";       Expression = { $_.whenCreated.ToString("yyyy-MM-dd HH:mm:ss") } }
    )

# Convierte el resultado a JSON y lo muestra por pantalla para que la API lo capture
$Usuarios | ConvertTo-Json -Compress
exit 0
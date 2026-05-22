# Se definen los datos que hay que pasarle al script al ejecutarlo.
# "Mandatory=$true" significa que son obligatorios
param (
    [Parameter(Mandatory=$true)] [string]$Nombre,
    [Parameter(Mandatory=$true)] [string]$Apellidos,
    [Parameter(Mandatory=$true)] [string]$Email,
    [Parameter(Mandatory=$true)] [string]$DNI
)

# --- Configuración del dominio ---
$Dominio     = "usuariosbiblioteca.local"
$DC          = "DC=usuariosbiblioteca,DC=local"
$OU_Provisional = "OU=Usuarios_Provisionales,$DC"

# Función para sustituir tildes, mayúsculas y ñ
function Quitar-Tildes {
    param([string]$Texto)
    $Texto = $Texto -replace 'á','a' -replace 'é','e' -replace 'í','i' -replace 'ó','o' -replace 'ú','u'
    $Texto = $Texto -replace 'Á','a' -replace 'É','e' -replace 'Í','i' -replace 'Ó','o' -replace 'Ú','u'
    $Texto = $Texto -replace 'ñ','n' -replace 'Ñ','n'
    return $Texto
}

#Quita espacios al principio y al final del nombre además de escribirlo en minúsculas. Hace lo mismo con los apellido
#pero solo coge el primero
$NombreLimpio    = Quitar-Tildes -Texto $Nombre.Trim().ToLower()
$ApellidoLimpio  = Quitar-Tildes -Texto ($Apellidos.Trim().Split(" ")[0].ToLower())
# Construye el nombre de usuario
$SamAccountBase = ($NombreLimpio+"."+$ApellidoLimpio)  
$SamAccount     = $SamAccountBase
# Contador que se usa si el nombre de usuario ya existe
$Contador       = 1

# Comprueba si ya existe el usuario y le añade un número que se incrementa al final
while (Get-ADUser -Filter { SamAccountName -eq $SamAccount } -ErrorAction SilentlyContinue) {
    $SamAccount = "$SamAccountBase$Contador"
    $Contador++
}

# Contraseña provisional generada con el DNI que el usuario debe cambiar al iniciar sesión
$PasswordProvisional = ConvertTo-SecureString "Prov_$DNI!" -AsPlainText -Force

# Marca de tiempo de creación de la cuenta 
$FechaCreacion = Get-Date -Format "yyyy-MM-dd HH:mm:ss"



# --- CREACIÓN DEL USUARIO ---
try {
    # Hace que el nombre sea único, por lo que si no lo es se añade el nombre de usuario entre paréntesis
    $NombreAD = if ($SamAccount -eq $SamAccountBase) { "$Nombre $Apellidos" } else { "$Nombre $Apellidos ($SamAccount)" }

    New-ADUser `
        -Name                  $NombreAD `
        -GivenName             $Nombre `
        -Surname               $Apellidos `
        -SamAccountName        $SamAccount `
        -UserPrincipalName     "$SamAccount@$Dominio" `
        -EmailAddress          $Email `
        -OtherAttributes       @{ description = "DNI:$DNI | Creado:$FechaCreacion" } `
        -Path                  $OU_Provisional `
        -AccountPassword       $PasswordProvisional `
        -Enabled               $true `
        -ChangePasswordAtLogon $true

    Write-Output "Usuario '$SamAccount' creado correctamente y pendiente de validación."
    # Significa que la ejecución del script es correcta. Esto lo usa la API para saber si está bien
    exit 0

} catch {
    Write-Error "Error al crear el usuario: $_"
    exit 3
}
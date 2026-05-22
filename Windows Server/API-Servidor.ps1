# --- Configuracion ---

# Puerto en el que la API va a escuchar peticiones del servidor Ubuntu
$Puerto       = 8080

# Contraseña que el servidor Ubuntu debe incluir en cada peticion para que la API la acepte
$TokenSecreto = "tokenBiblioteca2026"

# Ruta de la carpeta donde estan guardados los scripts de PowerShell en el servidor Windows
$ScriptsDir   = "C:\Scripts"

# Unica IP que tiene permitido llamar a la API
$IPPermitida  = "192.168.1.130"

# Numero maximo de peticiones permitidas por minuto desde la misma IP
$LimitePeticionesPorMinuto = 10

# Ruta del archivo donde se van a guardar los logs de todas las acciones
$LogPath = "C:\Scripts\Logs\api_auditoria.log"


# --- Crear carpeta de logs si no existe ---

# Split-Path extrae solo la ruta de la carpeta a partir de la ruta completa del archivo
$LogDir = Split-Path $LogPath

# Comprueba si la carpeta de logs existe y si no existe la crea
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir | Out-Null
}


# Diccionario que almacena las marcas de tiempo de las peticiones de cada IP
$RegistroPeticiones = @{}


# Escribe una linea en el log de auditoria con la fecha y hora actuales
function Escribir-Log {
    param([string]$Mensaje)
    # Construye la linea con la fecha y hora actual seguida del mensaje
    $Linea = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') | $Mensaje"
    # Muestra la linea por pantalla
    Write-Output $Linea
    # La escribe tambien en el archivo de log. SilentlyContinue evita errores si el archivo no es accesible
    Add-Content -Path $LogPath -Value $Linea -ErrorAction SilentlyContinue
}

# Construye y envia la respuesta JSON al servidor que hizo la peticion
function Enviar-Respuesta {
    param(
        # Objeto de respuesta HTTP de la peticion actual
        [System.Net.HttpListenerResponse]$Respuesta,  
        # Codigo HTTP 
        [int]$Codigo, 
        # "ok" si funciona, "error" si falla                                 
        [string]$Estado,    
        # Para explicar el resultado                           
        [string]$Mensaje                               
    )
    # Construccion del JSON de respuesta con el estado y el mensaje
    $Json  = '{"estado":"' + $Estado + '","mensaje":"' + $Mensaje + '"}'
    # Convierte el texto a bytes en formato UTF-8 para poder enviarlo por HTTP
    $Bytes = [System.Text.Encoding]::UTF8.GetBytes($Json)
    # Establece el codigo de estado HTTP de la respuesta
    $Respuesta.StatusCode      = $Codigo
    # Indica que el contenido de la respuesta es JSON en formato UTF-8
    $Respuesta.ContentType     = "application/json; charset=utf-8"
    # Indica cuantos bytes tiene el contenido para que el cliente sepa cuando termina
    $Respuesta.ContentLength64 = $Bytes.Length
    # Escribe los bytes en la salida y lo cierra para enviar la respuesta
    $Respuesta.OutputStream.Write($Bytes, 0, $Bytes.Length)
    $Respuesta.OutputStream.Close()
}

# Envia un JSON en bruto directamente sin envolverlo en estado/mensaje
# Se usa para el endpoint /listar que devuelve un array de usuarios
function Enviar-Json-Directo {
    param(
        [System.Net.HttpListenerResponse]$Respuesta,
        [int]$Codigo,
        [string]$Json
    )
    $Bytes = [System.Text.Encoding]::UTF8.GetBytes($Json)
    $Respuesta.StatusCode      = $Codigo
    $Respuesta.ContentType     = "application/json; charset=utf-8"
    $Respuesta.ContentLength64 = $Bytes.Length
    $Respuesta.OutputStream.Write($Bytes, 0, $Bytes.Length)
    $Respuesta.OutputStream.Close()
}

# Lee el cuerpo de la peticion HTTP y lo convierte en un objeto de PowerShell
function Leer-Json {
    param([System.Net.HttpListenerRequest]$Peticion)
    # StreamReader lee el cuerpo de la peticion como texto
    $Reader = New-Object System.IO.StreamReader($Peticion.InputStream, $Peticion.ContentEncoding)
    $Body   = $Reader.ReadToEnd()
    $Reader.Close()
    # ConvertFrom-Json convierte el texto JSON en un objeto de PowerShell
    try   { return $Body | ConvertFrom-Json }
    # Si el JSON esta mal formado devuelve null para poder detectarlo
    catch { return $null }
}

# Comprueba si una IP ha superado el limite de peticiones por minuto
function Limite-Superado {
    param([string]$IP)
    $Ahora = [datetime]::Now

    # Si la IP no tiene registro todavia, se crea una lista tipada de fechas para ella
    if (-not $RegistroPeticiones.ContainsKey($IP)) {
        $RegistroPeticiones[$IP] = [System.Collections.Generic.List[datetime]]::new()
    }

    # Filtra y conserva las marcas de tiempo del ultimo minuto
    # Se recorre la lista y se guardan solo las que tienen menos de 60 segundos
    $Recientes = [System.Collections.Generic.List[datetime]]::new()
    foreach ($Marca in $RegistroPeticiones[$IP]) {
        if (($Ahora - $Marca).TotalSeconds -lt 60) {
            $Recientes.Add($Marca)
        }
    }
    $RegistroPeticiones[$IP] = $Recientes

    # Si el numero de peticiones en el ultimo minuto es igual o mayor al limite, devuelve $true
    if ($RegistroPeticiones[$IP].Count -ge $LimitePeticionesPorMinuto) {
        return $true
    }

    # Si no se ha superado el limite, registra esta peticion y devuelve $false para permitirla
    $RegistroPeticiones[$IP].Add($Ahora)
    return $false
}

# Valida que el DNI tiene un formato correcto
function Validar-DNI {
    param([string]$DNI)
    return $DNI -match '^\d{8}[A-Za-z]$'
}

# Valida que el email tiene un formato correcto
function Validar-Email {
    param([string]$Email)
    return $Email -match '^[^@\s]+@[^@\s]+\.[^@\s]+$'
}

# Valida que un campo de texto no contiene caracteres que podrian usarse para inyectar comandos en PowerShell
function Validar-TextoSeguro {
    param([string]$Texto)
    return $Texto -notmatch '[;<>|&`$\(\)\{\}\[\]]'
}


# --- Inicio del escuchador HTTP ---

# Crea el objeto HttpListener que es el que abre el puerto y escucha peticiones HTTP
$Listener = New-Object System.Net.HttpListener

# Le dice al listener en que direccion y puerto tiene que escuchar
# El "+" significa que acepta peticiones desde cualquier IP, no solo desde localhost
$Listener.Prefixes.Add("http://+:$Puerto/")

try {
    # Arranca el listener, el puerto 8080 esta abierto y esperando peticiones
    $Listener.Start()
    Escribir-Log "API iniciada en el puerto $Puerto. Solo acepta peticiones de la IP $IPPermitida "
} catch {
    # Si no se puede abrir el puerto muestra el error y termina
    Write-Error "No se pudo iniciar la API en el puerto $Puerto : $_"
    exit 1
}


# --- Bucle principal ---

# El script se queda en este bucle indefinidamente esperando peticiones
# Cada vez que llega una la procesa y vuelve a esperar la siguiente
while ($Listener.IsListening) {
    try {
        # GetContext() espera bloqueado hasta que llega una peticion y cuando llega la captura
        $Contexto  = $Listener.GetContext()
        # Request contiene todos los datos de la peticion como el metodo, ruta, cuerpo...
        $Peticion  = $Contexto.Request
        # Response es el objeto con el que se envia la respuesta al cliente
        $Respuesta = $Contexto.Response

        # Extrae el metodo HTTP (POST o GET) y lo pone en mayusculas para compararlo despues
        $Metodo    = $Peticion.HttpMethod.ToUpper()
        # Extrae la ruta de la URL (/crear, /validar, /borrar, /listar) y la pone en minusculas
        $Ruta      = $Peticion.Url.AbsolutePath.ToLower().TrimEnd('/')
        # Extrae la IP del cliente que ha hecho la peticion
        $IPCliente = $Peticion.RemoteEndPoint.Address.ToString()

        # Escribe en el log que ha llegado una peticion, indicando metodo, ruta e IP
        Escribir-Log "[$Metodo] $Ruta desde $IPCliente"


        # --- Restriccion por IP ---
        # Solo se aceptan peticiones que vengan del servidor Ubuntu
        if ($IPCliente -ne $IPPermitida) {
            Escribir-Log "ACCESO DENEGADO: IP no permitida ($IPCliente)"
            # 403: la IP no tiene permiso para acceder
            Enviar-Respuesta -Respuesta $Respuesta -Codigo 403 -Estado "error" -Mensaje "Acceso denegado: IP no autorizada"
            continue
        }


        # --- Limite de peticiones por minuto ---
        # Llama a la funcion que comprueba si esta IP ha superado el limite de peticiones
        if (Limite-Superado -IP $IPCliente) {
            Escribir-Log "LIMITE SUPERADO: demasiadas peticiones desde $IPCliente"
            # 429: se han enviado demasiadas peticiones en poco tiempo
            Enviar-Respuesta -Respuesta $Respuesta -Codigo 429 -Estado "error" -Mensaje "Demasiadas peticiones. Espera un momento."
            continue
        }


        # --- Verificacion del token ---
        # Lee la cabecera X-API-Token que debe incluir el servidor Ubuntu en cada peticion
        $TokenRecibido = $Peticion.Headers["X-API-Token"]
        # Compara el token recibido con el token secreto configurado al inicio
        if ($TokenRecibido -ne $TokenSecreto) {
            Escribir-Log "TOKEN INCORRECTO desde $IPCliente"
            # 401: el token no es correcto, la peticion no esta autorizada
            Enviar-Respuesta -Respuesta $Respuesta -Codigo 401 -Estado "error" -Mensaje "Token de autorizacion incorrecto"
            continue
        }


        # Recibe los datos del formulario web y crea el usuario en la OU Usuarios_Provisionales
        if ($Metodo -eq "POST" -and $Ruta -eq "/crear") {

            $Datos = Leer-Json -Peticion $Peticion

            if (-not $Datos -or -not $Datos.nombre -or -not $Datos.apellidos -or -not $Datos.email -or -not $Datos.dni) {
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "Faltan campos obligatorios: nombre, apellidos, email, dni"
                continue
            }

            if (-not (Validar-TextoSeguro $Datos.nombre) -or -not (Validar-TextoSeguro $Datos.apellidos)) {
                Escribir-Log "DATOS INVALIDOS: caracteres no permitidos en nombre o apellidos"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "El nombre o los apellidos contienen caracteres no permitidos"
                continue
            }

            if (-not (Validar-Email $Datos.email)) {
                Escribir-Log "DATOS INVALIDOS: formato de email incorrecto ($($Datos.email))"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "El formato del email no es valido"
                continue
            }

            if (-not (Validar-DNI $Datos.dni)) {
                Escribir-Log "DATOS INVALIDOS: formato de DNI incorrecto ($($Datos.dni))"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "El formato del DNI no es valido (debe ser 8 numeros y una letra)"
                continue
            }

            $Salida = & "$ScriptsDir\Crear-Usuario.ps1" `
                -Nombre    $Datos.nombre `
                -Apellidos $Datos.apellidos `
                -Email     $Datos.email `
                -DNI       $Datos.dni 2>&1

            if ($LASTEXITCODE -eq 0) {
                Escribir-Log "USUARIO CREADO: $($Datos.nombre) $($Datos.apellidos) | Email: $($Datos.email) | DNI: $($Datos.dni)"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 200 -Estado "ok" -Mensaje "$Salida"
            } else {
                Escribir-Log "ERROR al crear usuario: $Salida"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 500 -Estado "error" -Mensaje "$Salida"
            }
        }


        # Habilita la cuenta y la mueve a Usuarios_Definitivos
        elseif ($Metodo -eq "POST" -and $Ruta -eq "/validar") {

            $Datos = Leer-Json -Peticion $Peticion

            if (-not $Datos -or -not $Datos.samaccount) {
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "Falta el campo obligatorio: samaccount"
                continue
            }

            if (-not (Validar-TextoSeguro $Datos.samaccount)) {
                Escribir-Log "DATOS INVALIDOS: caracteres no permitidos en samaccount ($($Datos.samaccount))"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "El nombre de usuario contiene caracteres no permitidos"
                continue
            }

            $Salida = & "$ScriptsDir\Validar-Usuario.ps1" -SamAccount $Datos.samaccount 2>&1

            if ($LASTEXITCODE -eq 0) {
                Escribir-Log "USUARIO VALIDADO: $($Datos.samaccount)"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 200 -Estado "ok" -Mensaje "$Salida"
            } else {
                Escribir-Log "ERROR al validar usuario: $Salida"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 500 -Estado "error" -Mensaje "$Salida"
            }
        }


        # Elimina un usuario del dominio
        elseif ($Metodo -eq "POST" -and $Ruta -eq "/borrar") {

            $Datos = Leer-Json -Peticion $Peticion

            if (-not $Datos -or -not $Datos.samaccount) {
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "Falta el campo obligatorio: samaccount"
                continue
            }

            if (-not (Validar-TextoSeguro $Datos.samaccount)) {
                Escribir-Log "DATOS INVALIDOS: caracteres no permitidos en samaccount ($($Datos.samaccount))"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 400 -Estado "error" -Mensaje "El nombre de usuario contiene caracteres no permitidos"
                continue
            }

            $Salida = & "$ScriptsDir\Borrar-Usuario.ps1" -SamAccount $Datos.samaccount 2>&1

            if ($LASTEXITCODE -eq 0) {
                Escribir-Log "USUARIO ELIMINADO: $($Datos.samaccount)"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 200 -Estado "ok" -Mensaje "$Salida"
            } else {
                Escribir-Log "ERROR al eliminar usuario: $Salida"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 500 -Estado "error" -Mensaje "$Salida"
            }
        }


        # Devuelve la lista de todos los usuarios del dominio en formato JSON
        elseif ($Metodo -eq "GET" -and $Ruta -eq "/listar") {

            # Ejecuta el script que obtiene todos los usuarios del dominio
            $Salida = & "$ScriptsDir\Listar-Usuarios.ps1" 2>&1

            if ($LASTEXITCODE -eq 0) {
                Escribir-Log "LISTADO DE USUARIOS solicitado"
                # Envia el JSON directamente sin envolverlo en estado/mensaje
                Enviar-Json-Directo -Respuesta $Respuesta -Codigo 200 -Json "$Salida"
            } else {
                Escribir-Log "ERROR al listar usuarios: $Salida"
                Enviar-Respuesta -Respuesta $Respuesta -Codigo 500 -Estado "error" -Mensaje "$Salida"
            }
        }


        # Cualquier otra ruta que no existe
        else {
            Escribir-Log "RUTA NO ENCONTRADA: $Ruta"
            # 404: la ruta solicitada no existe en la API
            Enviar-Respuesta -Respuesta $Respuesta -Codigo 404 -Estado "error" -Mensaje "Ruta no encontrada: $Ruta"
        }

    } catch {
        # Si ocurre algun error inesperado en el bucle se registra y se continua esperando peticiones
        Escribir-Log "ERROR en el bucle principal: $_"
    }
}

# Si el listener se detiene cierra el puerto
$Listener.Stop()
Escribir-Log "API DETENIDA"
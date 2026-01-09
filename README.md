# DocTranslator - PrestaShop Module

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PrestaShop](https://img.shields.io/badge/PrestaShop-8.0%2B-green.svg)
![License](https://img.shields.io/badge/license-MIT-orange.svg)

**DocTranslator** es un módulo híbrido para PrestaShop que permite traducir documentos (PDF, DOCX, DOC, TXT, ODT) de forma automática. Ofrece dos modos de funcionamiento: **API externa gratuita** (LibreTranslate) o **servidor local Python** (sin límites).

## 🌟 Características

- ✅ **Traducción de documentos** en múltiples formatos (PDF, DOCX, DOC, TXT, ODT)
- 🌐 **10 idiomas soportados**: Español, Inglés, Francés, Alemán, Italiano, Portugués, Chino, Japonés, Ruso, Árabe
- 🔄 **Modo híbrido**:
  - **API Externa**: Usa servidores LibreTranslate públicos (funciona en cualquier hosting)
  - **Servidor Local**: Usa tu propio servidor Python con Argos Translate (sin límites, ideal para VPS)
- 📊 **Historial de traducciones** con seguimiento de estado
- 🎯 **Interfaz intuitiva** con drag & drop para subir archivos
- 🔒 **Límites configurables** de tamaño de archivo y traducciones diarias
- 📈 **Contador de caracteres** traducidos
- 💾 **Descarga automática** de documentos traducidos

## 📋 Requisitos

### Requisitos Mínimos
- PrestaShop 8.0 o superior (compatible con PrestaShop 9)
- PHP 7.4 o superior
- Extensiones PHP: `curl`, `zip`, `json`
- MySQL 5.6 o superior

### Requisitos Adicionales (Modo Local)
- VPS o servidor dedicado
- Python 3.9 o superior
- Servidor de traducción Python con Argos Translate ([ver repositorio](https://github.com/droidhispalis/Translate_IA))

## 🚀 Instalación

### 1. Instalación del Módulo

1. Descarga el módulo desde este repositorio
2. Comprime la carpeta `doctranslator` en formato ZIP
3. En el back office de PrestaShop, ve a **Módulos > Module Manager**
4. Haz clic en **"Subir un módulo"** y selecciona el archivo ZIP
5. Instala el módulo

### 2. Configuración Inicial

1. Ve a **Módulos > Module Manager**
2. Busca **"DocTranslator"** y haz clic en **"Configurar"**
3. Selecciona el modo de traducción:

#### Opción A: Modo API (Recomendado para hosting compartido)
- Selecciona **"API Externa (LibreTranslate)"**
- Elige un servidor de la lista desplegable:
  - `https://libretranslate.com` (oficial)
  - `https://translate.argosopentech.com`
  - `https://translate.terraprint.co`
- (Opcional) Introduce una API Key si el servidor lo requiere
- Configura el tamaño máximo de archivo (por defecto: 10 MB)
- Configura el límite diario de traducciones (por defecto: 50)

#### Opción B: Modo Local (Para VPS)
- Selecciona **"Servidor Local (Python)"**
- Introduce la URL de tu servidor Python (ejemplo: `http://127.0.0.1:5000`)
- Instala y ejecuta el servidor de traducción ([instrucciones aquí](https://github.com/droidhispalis/Translate_IA))
- Configura límite diario en `0` para traducciones ilimitadas

4. Haz clic en **"Probar conexión"** para verificar la configuración
5. Guarda los cambios

## 📖 Uso

### Traducir un Documento

1. En el back office, ve a **Módulos > DocTranslator**
2. Selecciona el **idioma de origen** del documento
3. Selecciona el **idioma de destino**
4. Arrastra un archivo o haz clic para seleccionarlo
5. Haz clic en **"Traducir documento"**
6. Espera a que se complete el proceso (puede tardar según el tamaño)
7. Descarga el documento traducido

### Gestión del Historial

- Visualiza todas tus traducciones en la sección **"Historial de Traducciones"**
- Descarga documentos traducidos previamente
- Elimina traducciones antiguas
- Consulta el estado de cada traducción (Completado, Procesando, Error)

## 🏗️ Arquitectura Técnica

### Estructura de Archivos

```
doctranslator/
├── classes/
│   └── DocTranslatorProcessor.php    # Procesador de documentos
├── controllers/
│   └── admin/
│       ├── AdminDocTranslatorController.php  # Controlador principal
│       └── translator.tpl             # Template (legacy)
├── uploads/
│   ├── original/                      # Documentos originales
│   └── translated/                    # Documentos traducidos
├── views/
│   ├── css/
│   │   └── admin.css                  # Estilos del módulo
│   ├── js/
│   │   └── admin.js                   # JavaScript interactivo
│   └── templates/
│       ├── admin/
│       │   └── translator.tpl         # Interfaz principal
│       └── hook/
│           └── customer-account.tpl   # Hook para cuenta de cliente
├── config.xml                         # Configuración del módulo
├── config_es.xml                      # Configuración en español
├── doctranslator.php                  # Archivo principal del módulo
├── logo.png                           # Logo del módulo
└── README.md                          # Este archivo
```

### Base de Datos

El módulo crea la tabla `ps_doctranslator_translations` con la siguiente estructura:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_translation` | INT | ID único de la traducción |
| `id_employee` | INT | ID del empleado (back office) |
| `id_customer` | INT | ID del cliente (front office) |
| `original_filename` | VARCHAR(255) | Nombre del archivo original |
| `translated_filename` | VARCHAR(255) | Nombre del archivo traducido |
| `source_lang` | VARCHAR(5) | Código del idioma origen |
| `target_lang` | VARCHAR(5) | Código del idioma destino |
| `status` | VARCHAR(20) | Estado: pending, processing, completed, error |
| `file_size` | INT | Tamaño del archivo en bytes |
| `char_count` | INT | Número de caracteres traducidos |
| `error_message` | TEXT | Mensaje de error (si aplica) |
| `date_add` | DATETIME | Fecha de creación |
| `date_upd` | DATETIME | Fecha de actualización |

### Flujo de Traducción

#### Modo API (LibreTranslate)
1. El usuario sube un documento
2. `DocTranslatorProcessor` extrae el texto del documento
3. El texto se divide en fragmentos de 5000 caracteres
4. Cada fragmento se envía a la API de LibreTranslate
5. Los fragmentos traducidos se unen
6. Se genera el documento traducido
7. El usuario descarga el resultado

#### Modo Local (Python)
1. El usuario sube un documento
2. El archivo completo se envía al servidor Python vía POST
3. El servidor Python procesa el documento con Argos Translate
4. El servidor devuelve el archivo traducido en base64
5. El módulo guarda el archivo traducido
6. El usuario descarga el resultado

### APIs y Endpoints

#### Endpoints del Módulo

- **POST** `ajax.php?action=translate` - Traduce un documento
  - Parámetros: `document` (file), `source_lang`, `target_lang`
  - Respuesta: `{success: true, download_url: "...", char_count: 1234}`

- **GET** `ajax.php?action=download&file=filename` - Descarga un documento traducido

- **POST** `ajax.php?action=delete&id=123` - Elimina una traducción del historial

#### API Externa (LibreTranslate)

- **GET** `/languages` - Obtiene idiomas disponibles
- **POST** `/translate` - Traduce texto
  ```json
  {
    "q": "texto a traducir",
    "source": "es",
    "target": "en",
    "format": "text"
  }
  ```

#### API Local (Servidor Python)

- **GET** `/api/v1/status` - Verifica estado del servidor
- **POST** `/api/v1/translate` - Traduce documento completo
  - Parámetros: `file`, `source_lang`, `target_lang`
- **POST** `/api/v1/translate/text` - Traduce solo texto
  ```json
  {
    "text": "texto a traducir",
    "source_lang": "es",
    "target_lang": "en"
  }
  ```

## 🔧 Configuración Avanzada

### Límites de Traducción

Edita las siguientes constantes en la configuración del módulo:

- `DOCTRANSLATOR_MAX_SIZE`: Tamaño máximo de archivo en MB (por defecto: 10)
- `DOCTRANSLATOR_DAILY_LIMIT`: Límite diario de traducciones (0 = sin límite)

### Servidores LibreTranslate Personalizados

Puedes añadir tus propios servidores LibreTranslate editando el array `LIBRE_TRANSLATE_MIRRORS` en `doctranslator.php`:

```php
const LIBRE_TRANSLATE_MIRRORS = [
    'https://libretranslate.com',
    'https://translate.argosopentech.com',
    'https://tu-servidor-personalizado.com',
];
```

### Seguridad

Los directorios de uploads están protegidos con:
- `.htaccess` con `Deny from all`
- `index.php` con redirección

## 🐛 Solución de Problemas

### Error: "No se pudo conectar al servidor"

**Modo API:**
- Verifica que el servidor LibreTranslate esté disponible
- Prueba con otro servidor de la lista
- Comprueba la configuración del firewall

**Modo Local:**
- Verifica que el servidor Python esté ejecutándose
- Comprueba la URL configurada (debe incluir `http://`)
- Revisa los logs del servidor Python

### Error: "Archivo demasiado grande"

- Aumenta el límite en la configuración del módulo
- Verifica los límites de PHP (`upload_max_filesize`, `post_max_size`)
- Considera dividir el documento en partes más pequeñas

### Error: "Límite diario alcanzado"

- Aumenta el límite en la configuración
- Espera hasta el día siguiente
- Usa el modo local con límite en 0 (ilimitado)

### Traducciones incompletas o incorrectas

- Los servidores públicos pueden tener limitaciones de calidad
- Considera usar el modo local para mejor calidad
- Verifica que el idioma de origen sea correcto

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz un fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Añade nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crea un Pull Request

## 📝 Changelog

### v1.0.0 (2024)
- ✨ Lanzamiento inicial
- ✅ Soporte para PDF, DOCX, DOC, TXT, ODT
- ✅ Modo API con LibreTranslate
- ✅ Modo local con servidor Python
- ✅ Historial de traducciones
- ✅ Interfaz drag & drop
- ✅ Compatible con PrestaShop 8 y 9

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 👨‍💻 Autor

**DocTranslator Team**
- GitHub: [@droidhispalis](https://github.com/droidhispalis)
- Repositorio del servidor Python: [Translate_IA](https://github.com/droidhispalis/Translate_IA)

## 🔗 Enlaces Relacionados

- [PrestaShop Documentation](https://devdocs.prestashop.com/)
- [LibreTranslate](https://libretranslate.com/)
- [Argos Translate](https://github.com/argosopentech/argos-translate)
- [Servidor Python de Traducción](https://github.com/droidhispalis/Translate_IA)

## 💡 Soporte

Si encuentras algún problema o tienes alguna pregunta:

1. Revisa la sección de [Solución de Problemas](#-solución-de-problemas)
2. Busca en los [Issues](https://github.com/droidhispalis/DocTranslator_PS_Hibrid/issues) existentes
3. Crea un nuevo Issue con detalles del problema

---

**¿Te gusta este proyecto?** Dale una ⭐ en GitHub!

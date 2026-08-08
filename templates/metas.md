Necesito que revises e implementes correctamente la generación de vistas previas de enlaces para WhatsApp, Facebook y otros servicios compatibles con Open Graph en mi proyecto.

## Contexto técnico

Mi proyecto utiliza:

* PHP
* Symfony
* Twig
* Alpine.js
* Backend renderizado del lado del servidor
* URLs públicas de productos/autopartes

El objetivo es que cuando comparta una URL de una pieza, por ejemplo:

`https://midominio.com/pieza/12345`

WhatsApp pueda recuperar correctamente:

* Imagen principal
* Título del producto
* Descripción
* URL canónica

y generar una vista previa similar a la que genera Mercado Libre cuando se comparte un enlace.

IMPORTANTE:

No quiero solamente agregar etiquetas `<meta>` de forma superficial.

Quiero que revises todo el flujo completo del servidor y determines si WhatsApp/Meta realmente podrá acceder, interpretar y descargar la información correctamente.

---

# 1. AUDITAR PRIMERO LA IMPLEMENTACIÓN ACTUAL

Antes de modificar código, revisa:

* Rutas Symfony relacionadas con productos/piezas.
* Controllers.
* Templates Twig.
* Template base.
* Herencia de templates Twig.
* Datos enviados desde Controller hacia Twig.
* Entidad/modelo del producto.
* Sistema actual de almacenamiento de imágenes.
* URLs públicas de las imágenes.
* Configuración de Symfony relacionada con URLs absolutas.
* Configuración de Nginx/Apache, si está disponible.
* Redirecciones HTTP/HTTPS.
* Seguridad/autenticación de las páginas.
* Cualquier middleware, firewall o mecanismo que pueda bloquear crawlers.
* `robots.txt`.
* Headers HTTP.
* Canonical URLs.
* Alpine.js utilizado dentro de la página.

Determina si actualmente una petición HTTP directa a una URL de producto devuelve en el HTML inicial toda la información necesaria.

No asumas que Alpine.js o JavaScript será ejecutado por WhatsApp.

La información Open Graph debe existir directamente en el HTML generado por Symfony/Twig.

---

# 2. IMPLEMENTAR OPEN GRAPH CORRECTAMENTE

La página de cada producto debe generar dinámicamente, como mínimo:

```html
<meta property="og:title" content="Título del producto">
<meta property="og:description" content="Descripción del producto">
<meta property="og:image" content="https://midominio.com/ruta/imagen.jpg">
<meta property="og:url" content="https://midominio.com/pieza/12345">
<meta property="og:type" content="product">
<meta property="og:site_name" content="YonkEROS">
```

También agrega:

```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Título del producto">
<meta name="twitter:description" content="Descripción del producto">
<meta name="twitter:image" content="https://midominio.com/ruta/imagen.jpg">
```

No dupliques etiquetas si ya existen.

---

# 3. HACERLO CORRECTAMENTE CON TWIG

Revisa el template base del proyecto.

Prefiero una arquitectura donde el `<head>` general tenga bloques Twig reutilizables.

Por ejemplo, si es adecuado para la arquitectura existente:

```twig
<title>{% block title %}YonkEROS{% endblock %}</title>

{% block meta %}
{% endblock %}
```

Y posteriormente en la página del producto:

```twig
{% block meta %}

<meta property="og:title"
      content="{{ producto.titulo }}">

<meta property="og:description"
      content="{{ producto.descripcion }}">

<meta property="og:image"
      content="{{ imagenAbsoluta }}">

<meta property="og:url"
      content="{{ urlAbsoluta }}">

<meta property="og:type"
      content="product">

<meta property="og:site_name"
      content="YonkEROS">

{% endblock %}
```

Pero NO copies esto ciegamente.

Primero revisa cómo está estructurado realmente el proyecto y adapta la solución a nuestra arquitectura actual.

---

# 4. URLS ABSOLUTAS

Este punto es crítico.

Verifica que:

`og:image`

NO genere algo como:

```text
/uploads/productos/123.jpg
```

Debe generar una URL absoluta:

```text
https://midominio.com/uploads/productos/123.jpg
```

Igualmente:

`og:url`

debe contener una URL absoluta.

Utiliza las herramientas nativas de Symfony/Twig cuando sea posible.

Por ejemplo:

```twig
{{ absolute_url(asset('uploads/productos/' ~ producto.imagen)) }}
```

o:

```twig
{{ url('nombre_ruta_producto', {id: producto.id}) }}
```

Revisa cuál es la implementación correcta según nuestras rutas reales.

---

# 5. IMAGEN OPEN GRAPH

Revisa específicamente cómo almacenamos las imágenes.

La imagen que utilicemos como:

`og:image`

debe:

* Ser pública.
* No requerir autenticación.
* Poder descargarse directamente mediante HTTP GET.
* Responder HTTP 200.
* Utilizar HTTPS.
* Tener un Content-Type correcto, por ejemplo:

  * image/jpeg
  * image/png
  * image/webp, únicamente si es apropiado.
* No depender de JavaScript.
* No requerir cookies.
* No utilizar una URL temporal que expire rápidamente.
* No estar bloqueada para crawlers.

Si el producto tiene varias imágenes, utiliza la imagen principal.

Si no existe imagen, implementa una imagen fallback adecuada de YonkEROS.

Ejemplo conceptual:

```text
https://midominio.com/images/og/default-product.jpg
```

No inventes la ruta. Revisa dónde deberían almacenarse los assets públicos dentro del proyecto.

---

# 6. AGREGAR INFORMACIÓN DE IMAGEN

Cuando sea posible, agrega:

```html
<meta property="og:image:secure_url" content="...">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
```

Pero no inventes dimensiones.

Si el sistema conoce las dimensiones reales, utilízalas.

Si no las conoce, determina si vale la pena implementar estos campos o dejarlos fuera.

---

# 7. TÍTULO DE LA PIEZA

Construye un título descriptivo utilizando los datos reales disponibles.

Por ejemplo:

```text
Fascia delantera Toyota Tacoma 2016-2023
```

o:

```text
Puerta delantera derecha Toyota Tacoma 2016-2023
```

Utiliza campos reales del producto como:

* pieza
* marca
* modelo
* año inicial
* año final
* lado
* posición

NO inventes campos si nuestra entidad no los contiene.

Revisa primero el modelo real.

---

# 8. DESCRIPCIÓN

Genera una descripción corta y útil.

Ejemplo:

```text
Fascia delantera Toyota Tacoma 2016-2023. Disponible en YonkEROS. Consulta fotos, precio y disponibilidad.
```

No incluyas textos excesivamente largos.

Escapa correctamente caracteres especiales mediante Twig.

Evita HTML dentro del atributo `content`.

---

# 9. PRECIO

Revisa si conviene utilizar Open Graph adicional o metadatos de producto.

Si existe precio, evalúa incluir:

```html
<meta property="product:price:amount" content="1600.00">
<meta property="product:price:currency" content="MXN">
```

Utiliza solamente datos reales.

No hagas que el funcionamiento principal dependa de estos campos.

La prioridad es:

1. og:title
2. og:description
3. og:image
4. og:url

---

# 10. CANONICAL

Agrega o verifica:

```html
<link rel="canonical" href="URL_ABSOLUTA_PRODUCTO">
```

La URL canonical debe corresponder a la URL pública definitiva de ese producto.

Evita URLs duplicadas con diferentes parámetros si no son necesarias.

---

# 11. VERIFICAR HTTP Y REDIRECCIONES

Haz una auditoría conceptual/técnica de una URL de producto.

Ejemplo:

```bash
curl -I https://midominio.com/pieza/12345
```

Debe terminar idealmente en:

```text
HTTP/2 200
```

Si existe redirección HTTP → HTTPS está bien.

Pero verifica que no haya:

* ciclos de redirección
* redirecciones hacia login
* HTTP 401
* HTTP 403
* HTTP 404
* HTTP 500

También prueba:

```bash
curl -L https://midominio.com/pieza/12345
```

y verifica que el HTML recibido contenga realmente:

```text
og:title
og:description
og:image
og:url
```

IMPORTANTE:

Quiero comprobar el HTML recibido desde el servidor, NO el DOM generado después por JavaScript.

---

# 12. SIMULAR UN CRAWLER

Si es posible dentro del entorno actual, prueba la página con distintos User-Agent.

Por ejemplo un User-Agent similar a:

```text
facebookexternalhit
```

Verifica que el servidor no bloquee esa petición.

No hagas lógica especial para engañar al crawler.

El crawler y el usuario normal deberían recibir información coherente.

---

# 13. REVISAR robots.txt

Verifica:

```text
/robots.txt
```

y asegúrate de que no estemos bloqueando accidentalmente las páginas de producto o las imágenes.

Por ejemplo, identifica configuraciones problemáticas como:

```text
User-agent: *
Disallow: /
```

No modifiques robots.txt si no es necesario.

Explícame primero cualquier problema encontrado.

---

# 14. REVISAR SEGURIDAD SYMFONY

Revisa `security.yaml`.

Las URLs públicas de producto que se comparten por WhatsApp deben poder accederse SIN iniciar sesión.

Comprueba que una ruta como:

```text
/pieza/{id}
```

no esté protegida accidentalmente.

Lo mismo aplica para imágenes.

No relajes la seguridad global del sistema.

Únicamente asegúrate de que el contenido que deliberadamente queremos compartir sea público.

---

# 15. ALPINE.JS

Revisa si actualmente:

* título
* precio
* descripción
* imagen

se cargan mediante Alpine.js después de cargar la página.

Si es así, modifica la arquitectura de esa página para que los datos necesarios para Open Graph provengan directamente de Symfony/Twig.

Alpine.js puede continuar utilizándose para la interfaz interactiva.

Pero las etiquetas OG NO deben depender de:

```javascript
x-data
x-init
fetch()
axios
```

ni ninguna petición AJAX posterior.

---

# 16. CACHE

Ten en cuenta que WhatsApp/Meta puede guardar la vista previa de una URL durante un tiempo.

Por esta razón:

* No concluyas que la implementación está mal únicamente porque WhatsApp continúe mostrando una imagen anterior.
* Primero valida el HTML mediante curl.
* Después valida el contenido Open Graph.
* Identifica si el problema restante puede ser caché de Meta.

No implementes parámetros aleatorios o trucos para saltarse caché como solución permanente.

---

# 17. HEADERS DEL SERVIDOR

Verifica que las páginas devuelvan:

```text
Content-Type: text/html
```

y las imágenes:

```text
Content-Type: image/jpeg
```

o el MIME correspondiente.

Revisa también si existen políticas demasiado restrictivas que pudieran impedir al crawler descargar imágenes.

No elimines configuraciones de seguridad sin analizar sus consecuencias.

---

# 18. SERVER-SIDE RENDERING

La arquitectura correcta que quiero conseguir es conceptualmente:

```text
WhatsApp
    |
    | HTTP GET
    v
Symfony Router
    |
    v
Controller
    |
    +----> Base de datos
    |
    v
Twig
    |
    v
HTML COMPLETO
    |
    +---- og:title
    +---- og:description
    +---- og:image
    +---- og:url
    |
    v
WhatsApp construye preview
```

No quiero:

```text
WhatsApp
    |
    v
HTML vacío
    |
    v
Alpine/JavaScript
    |
    v
fetch producto
```

porque un crawler puede no ejecutar ese proceso.

---

# 19. NO CREAR UNA PÁGINA ESPECIAL PARA WHATSAPP SI NO ES NECESARIO

La URL normal del producto debería servir tanto para:

* Usuarios
* WhatsApp
* Facebook
* Telegram
* Motores de búsqueda

Evita crear rutas como:

```text
/whatsapp-preview/123
```

salvo que exista una razón arquitectónica real.

Prefiero que:

```text
/pieza/123
```

sea una página correctamente renderizada desde Symfony.

---

# 20. VERIFICACIÓN FINAL

Cuando termines, selecciona una URL real de producto y realiza, si el entorno permite hacerlo, estas verificaciones:

### A. Página

```bash
curl -I URL
```

### B. HTML

```bash
curl -L URL
```

Confirma que aparezcan:

```text
og:title
og:description
og:image
og:url
```

### C. Imagen

Haz una petición directamente a la URL contenida en:

```text
og:image
```

y confirma:

```text
HTTP 200
```

y el Content-Type correcto.

### D. URL pública

Comprueba que ninguna de estas operaciones requiera:

* sesión
* cookies
* JWT
* API token
* autenticación

---

# 21. IMPORTANTE: NO ROMPER EL PROYECTO

No realices cambios masivos.

No cambies:

* arquitectura general
* rutas actuales
* entidades
* base de datos
* sistema de autenticación

salvo que exista una razón indispensable.

Haz el cambio mínimo necesario y reutiliza la arquitectura actual.

---

# RESULTADO QUE ESPERO

Al finalizar quiero un reporte concreto dividido en:

## 1. Estado actual

Explícame qué encontraste.

Por ejemplo:

```text
✅ La página se renderiza mediante Twig.
✅ La imagen es pública.
❌ No existen etiquetas Open Graph.
❌ og:image actualmente sería relativa.
✅ La página devuelve HTTP 200.
```

## 2. Problemas encontrados

Lista solamente problemas reales encontrados en el proyecto.

## 3. Cambios realizados

Indica exactamente:

* archivos modificados
* código agregado
* código eliminado
* razones de cada modificación

## 4. Código final

Muéstrame los bloques relevantes de Twig/PHP modificados.

## 5. Pruebas

Muéstrame los resultados de las verificaciones realizadas.

## 6. Resultado esperado en WhatsApp

Indica exactamente qué debería obtener WhatsApp, por ejemplo:

```text
Imagen:
https://midominio.com/uploads/productos/12345.jpg

Título:
Fascia delantera Toyota Tacoma 2016-2023

Descripción:
Disponible en YonkEROS. Consulta precio, fotografías y disponibilidad.

URL:
https://midominio.com/pieza/12345
```

## REGLA PRINCIPAL

No des por terminado el trabajo únicamente porque las etiquetas `<meta>` existan.

Debes verificar toda la cadena:

```text
URL pública
→ Symfony
→ Controller
→ datos correctos
→ Twig
→ HTML inicial
→ Open Graph
→ imagen pública
→ HTTP 200
→ crawler de Meta/WhatsApp
```

La finalidad es conseguir una implementación robusta comparable conceptualmente con sitios de comercio electrónico como Mercado Libre, donde al compartir una URL WhatsApp puede recuperar automáticamente la fotografía y la información principal del producto.

# Gemini Polylang Auto Translator
**Plugin que integra los modelos de Inteligencia Artificial de Google Gemini con Polylang para agilizar la traducción multilingüe en WordPress.**

#### Características principales:

* **Generación de borradores desde el listado:** Crea traducciones vinculadas en Polylang directamente desde la tabla/lista de entradas, páginas o cpt's.
* **Traducción directa en el editor:** Incluye un metabox lateral en Gutenberg ("Traducir AI") para actualizar el contenido, extracto y metadatos de la entrada actual sin crear duplicados innecesarios.
* **Respeto de código e integridad del editor:** Conserva la estructura de bloques de Gutenberg (`<!-- wp:... -->`), shortcodes, atributos y etiquetas HTML.
* **Soporte completo de metadatos:** Mapea y traduce automáticamente categorías, etiquetas, taxonomías personalizadas y la asignación de la imagen destacada.
* **Modelo configurable:** Permite seleccionar la clave API de Google AI Studio y definir el modelo exacto a utilizar (por ejemplo, `gemini-3.5-flash` o `gemini-3.5-flash-lite`).
* **Compatible con Wordpress:** 7.0.2
* **Compatible con Polylang:** 3.8.6

#### Installation

1. Sube la carpeta `gemini-polylang-translator` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú 'Plugins' en WordPress.
3. Asegúrate de tener **Polylang** activo con al menos dos idiomas configurados.
4. Ve a **Ajustes > Gemini Translator** para introducir tu Gemini API Key de Google AI Studio.

#### Frequently Asked Questions

##### ¿Qué ocurre con los campos de la ficha técnica/metadatos al traducir?
Todos los metadatos se copian íntegramente al post traducido para mantener la integridad de los datos técnicos (versiones, enlaces, requisitos).

##### Error "Falta API Key de Gemini"
Comprueba que la clave de API guardada en Ajustes > Gemini Translator sea válida y no contenga espacios accidentales.

#### Changelog

##### 1.2.4
* Añadida sincronización y clonación automática de post meta (fichas técnicas) en ambos flujos de traducción.

##### 1.2.3
* Ajuste y soporte para modelos recientes de Gemini AI.

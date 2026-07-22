<?php
/**
 * Plugin Name: Gemini Polylang Auto Translator
 * Plugin URI:  https://github.com/ektorcaba/gemini-polylang-translator
 * Description: Traduce entradas, CPTs, extractos, imagen destacada, metadatos y taxonomías utilizando la API de Google Gemini integrada con Polylang.
 * Version:     1.2.4
 * Author:      ektorcaba
 * License:     GPL-2.0+
 * Text Domain: gemini-polylang-translator
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('Gemini_Polylang_Translator')) {

    class Gemini_Polylang_Translator {

        private $option_name = 'gpt_settings';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_init', [$this, 'handle_translation_action']);
            add_action('admin_init', [$this, 'handle_current_post_translation_action']);
            add_action('admin_init', [$this, 'setup_row_actions']);
            add_action('add_meta_boxes', [$this, 'add_translation_meta_box']);
            add_action('admin_notices', [$this, 'render_admin_notices']);
        }

        /* ------------------------------------------------------------------------
         * 1. CONFIGURACIÓN Y AJUSTES
         * ------------------------------------------------------------------------ */

        public function add_settings_page() {
            add_options_page(
                'Gemini Translator',
                'Gemini Translator',
                'manage_options',
                'gemini-translator',
                [$this, 'render_settings_page']
            );
        }

        public function register_settings() {
            register_setting($this->option_name, $this->option_name);
        }

        public function render_settings_page() {
            $options = get_option($this->option_name, []);
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $model = !empty($options['model']) ? $options['model'] : '';
            $selected_cpts = (isset($options['post_types']) && is_array($options['post_types'])) ? $options['post_types'] : ['post', 'page'];

            $post_types = get_post_types(['public' => true], 'objects');
            ?>
            <div class="wrap">
                <h1>Configuración de Gemini Polylang Translator</h1>
                <form method="post" action="options.php">
                    <?php settings_fields($this->option_name); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="api_key">Gemini API Key</label></th>
                            <td>
                                <input type="password" id="api_key" name="<?php echo esc_attr($this->option_name); ?>[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required>
                                <p class="description">Consigue tu API Key gratuita en <a href="https://aistudio.google.com/" target="_blank" rel="noopener">Google AI Studio</a>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="model">Nombre del Modelo</label></th>
                            <td>
                                <input type="text" id="model" name="<?php echo esc_attr($this->option_name); ?>[model]" value="<?php echo esc_attr($model); ?>" placeholder="gemini-3.5-flash-lite" class="regular-text">
                                <p class="description">Escribe el identificador exacto del modelo de Gemini (por ejemplo: <code>gemini-3.5-flash-lite</code>, <code>gemini-2.5-flash</code> o <code>gemini-2.0-flash</code>).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tipos de Contenido (Post Types)</th>
                            <td>
                                <?php foreach ($post_types as $pt): ?>
                                    <label style="display:block; margin-bottom: 5px;">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[post_types][]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected_cpts, true)); ?>>
                                        <?php echo esc_html($pt->label); ?> (<code><?php echo esc_html($pt->name); ?></code>)
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }

        /* ------------------------------------------------------------------------
         * 2. META BOX EN LA PANTALLA DE EDICIÓN DEL POST
         * ------------------------------------------------------------------------ */

        public function add_translation_meta_box() {
            $options = get_option($this->option_name, []);
            $selected_cpts = (isset($options['post_types']) && is_array($options['post_types'])) ? $options['post_types'] : ['post', 'page'];

            foreach ($selected_cpts as $pt) {
                add_meta_box(
                    'gpt_translation_box',
                    'Traducción con Gemini AI',
                    [$this, 'render_translation_meta_box'],
                    $pt,
                    'side',
                    'high'
                );
            }
        }

        public function render_translation_meta_box($post) {
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=gpt_translate_current_post&post_id=' . $post->ID),
                'gpt_translate_current_nonce_' . $post->ID
            );
            ?>
            <div style="padding: 5px 0;">
                <p style="margin-top:0; font-size: 13px; color: #666;">Traduce el contenido, imagen destacada, metadatos y taxonomías de esta entrada directamente con Gemini sin crear una entrada duplicada.</p>
                <a href="<?php echo esc_url($url); ?>" class="button button-primary button-large" style="width:100%; text-align:center;">
                    Traducir AI
                </a>
            </div>
            <?php
        }

        public function render_admin_notices() {
            if (isset($_GET['gpt_status']) && $_GET['gpt_status'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Gemini Translator:</strong> Entrada, imagen destacada, metadatos y taxonomías traducidas correctamente.</p></div>';
            }
        }

        /* ------------------------------------------------------------------------
         * 3. ACCIONES EN LISTADOS Y PROCESOS
         * ------------------------------------------------------------------------ */

        public function setup_row_actions() {
            $options = get_option($this->option_name, []);
            $selected_cpts = (isset($options['post_types']) && is_array($options['post_types'])) ? $options['post_types'] : ['post', 'page'];

            foreach ($selected_cpts as $pt) {
                if ($pt === 'page') {
                    add_filter('page_row_actions', [$this, 'add_translation_row_action'], 10, 2);
                } else {
                    add_filter("{$pt}_row_actions", [$this, 'add_translation_row_action'], 10, 2);
                }
            }
        }

        public function add_translation_row_action($actions, $post) {
            if (!function_exists('pll_get_post_language')) return $actions;
            if (!function_exists('pll_languages_list')) return $actions;

            $current_lang = pll_get_post_language($post->ID, 'slug');
            $all_langs = pll_languages_list();

            if (!$current_lang) return $actions;
            if (!is_array($all_langs)) return $actions;
            if (count($all_langs) < 2) return $actions;

            $target_langs = array_diff($all_langs, [$current_lang]);
            $target_lang = reset($target_langs);

            if (!$target_lang) return $actions;

            $existing_translation_id = function_exists('pll_get_post') ? pll_get_post($post->ID, $target_lang) : false;
            if ($existing_translation_id) return $actions;

            $url = wp_nonce_url(
                admin_url('admin-post.php?action=gpt_generate_translation&post_id=' . $post->ID),
                'gpt_translate_nonce_' . $post->ID
            );

            $actions['gpt_translate'] = sprintf(
                '<a href="%s" style="color: #2271b1; font-weight: bold;">%s</a>',
                esc_url($url),
                esc_html__('Generar traducción', 'textdomain')
            );

            return $actions;
        }

        /* Acción desde el botón de la barra lateral "Traducir AI" */
        public function handle_current_post_translation_action() {
            if (!isset($_GET['action'])) return;
            if ($_GET['action'] !== 'gpt_translate_current_post') return;

            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

            if (!$post_id) wp_die('Petición no válida.');
            if (!check_admin_referer('gpt_translate_current_nonce_' . $post_id)) wp_die('Token de seguridad expirado.');
            if (!current_user_can('edit_post', $post_id)) wp_die('No tienes permisos suficientes.');

            $options = get_option($this->option_name, []);
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $model = !empty($options['model']) ? trim($options['model']) : 'gemini-3.5-flash-lite';

            if (empty($api_key)) wp_die('Debes configurar la API Key de Gemini en Ajustes > Gemini Translator.');

            $current_post = get_post($post_id);
            if (!$current_post) wp_die('Post no encontrado.');

            $current_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post_id, 'slug') : '';
            $all_langs = function_exists('pll_languages_list') ? pll_languages_list() : [];

            $source_post = $current_post;
            $source_lang = $current_lang;
            $target_lang = $current_lang;

            if (function_exists('pll_get_post_translations')) {
                $translations = pll_get_post_translations($post_id);
                foreach ($translations as $lang => $translated_id) {
                    if ($translated_id !== $post_id) {
                        $linked_post = get_post($translated_id);
                        if ($linked_post && !empty($linked_post->post_content)) {
                            $source_post = $linked_post;
                            $source_lang = $lang;
                            break;
                        }
                    }
                }
            }

            if ($source_post->ID === $post_id) {
                if (is_array($all_langs) && count($all_langs) >= 2) {
                    $target_langs = array_diff($all_langs, [$source_lang]);
                    $target_lang = reset($target_langs);
                }
            }

            // Traducir partes obligatorias
            $translated_title = $this->translate_with_gemini($source_post->post_title, $source_lang, $target_lang, $api_key, $model, false);
            if ($translated_title === false) {
                wp_die('Error de traducción en el título desde la API de Gemini. No se ha realizado ningún cambio en la entrada.');
            }

            $translated_content = $this->translate_with_gemini($source_post->post_content, $source_lang, $target_lang, $api_key, $model, true);
            if ($translated_content === false) {
                wp_die('Error de traducción en el contenido desde la API de Gemini. No se ha realizado ningún cambio en la entrada.');
            }

            $translated_excerpt = '';
            if (!empty($source_post->post_excerpt)) {
                $translated_excerpt = $this->translate_with_gemini($source_post->post_excerpt, $source_lang, $target_lang, $api_key, $model, false);
                if ($translated_excerpt === false) {
                    wp_die('Error de traducción en el extracto desde la API de Gemini. No se ha realizado ningún cambio en la entrada.');
                }
            }

            // Actualizar entrada actual
            wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $translated_title,
                'post_content' => $translated_content,
                'post_excerpt' => $translated_excerpt,
            ]);

            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($post_id, $target_lang);
            }

            // Copiar la imagen destacada
            $this->copy_featured_image($source_post->ID, $post_id, $target_lang);

            // Copiar metadatos (Ficha técnica)
            $this->copy_post_meta($source_post->ID, $post_id);

            // Copiar y traducir taxonomías
            $this->copy_and_translate_taxonomies($source_post->ID, $post_id, $source_lang, $target_lang, $api_key, $model);

            wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit&gpt_status=success'));
            exit;
        }

        /* Acción desde la lista general de posts ("Generar traducción") */
        public function handle_translation_action() {
            if (!isset($_GET['action'])) return;
            if ($_GET['action'] !== 'gpt_generate_translation') return;

            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

            if (!$post_id) wp_die('Petición no válida.');
            if (!check_admin_referer('gpt_translate_nonce_' . $post_id)) wp_die('Token expirado.');
            if (!current_user_can('edit_post', $post_id)) wp_die('Permisos insuficientes.');

            $options = get_option($this->option_name, []);
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $model = !empty($options['model']) ? trim($options['model']) : 'gemini-3.5-flash-lite';

            if (empty($api_key)) wp_die('Falta API Key de Gemini.');

            $source_post = get_post($post_id);
            if (!$source_post) wp_die('Post no encontrado.');

            $source_lang = pll_get_post_language($post_id, 'slug');
            $all_langs = pll_languages_list();

            if (!is_array($all_langs) || count($all_langs) < 2 || !$source_lang) {
                wp_die('Error de configuración en idiomas de Polylang.');
            }

            $target_langs = array_diff($all_langs, [$source_lang]);
            $target_lang = reset($target_langs);

            // Validar respuestas antes de crear el post borrador
            $translated_title = $this->translate_with_gemini($source_post->post_title, $source_lang, $target_lang, $api_key, $model, false);
            if ($translated_title === false) {
                wp_die('Error de traducción en el título desde Gemini. Proceso abortado sin duplicar el post.');
            }

            $translated_content = $this->translate_with_gemini($source_post->post_content, $source_lang, $target_lang, $api_key, $model, true);
            if ($translated_content === false) {
                wp_die('Error de traducción en el contenido desde Gemini. Proceso abortado sin duplicar el post.');
            }

            $translated_excerpt = '';
            if (!empty($source_post->post_excerpt)) {
                $translated_excerpt = $this->translate_with_gemini($source_post->post_excerpt, $source_lang, $target_lang, $api_key, $model, false);
                if ($translated_excerpt === false) {
                    wp_die('Error de traducción en el extracto desde Gemini. Proceso abortado sin duplicar el post.');
                }
            }

            $new_post_id = wp_insert_post([
                'post_title'    => $translated_title,
                'post_content'  => $translated_content,
                'post_excerpt'  => $translated_excerpt,
                'post_status'   => 'draft',
                'post_type'     => $source_post->post_type,
                'post_author'   => get_current_user_id(),
            ]);

            if (is_wp_error($new_post_id)) {
                wp_die('Error al crear el post: ' . $new_post_id->get_error_message());
            }

            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($new_post_id, $target_lang);
            }

            if (function_exists('pll_get_post_translations') && function_exists('pll_save_post_translations')) {
                $translations = pll_get_post_translations($post_id);
                if (!is_array($translations)) $translations = [$source_lang => $post_id];
                $translations[$target_lang] = $new_post_id;
                pll_save_post_translations($translations);
            }

            // Copiar la imagen destacada
            $this->copy_featured_image($post_id, $new_post_id, $target_lang);

            // Copiar metadatos (Ficha técnica)
            $this->copy_post_meta($post_id, $new_post_id);

            // Copiar y traducir taxonomías
            $this->copy_and_translate_taxonomies($post_id, $new_post_id, $source_lang, $target_lang, $api_key, $model);

            wp_redirect(get_edit_post_link($new_post_id, 'raw'));
            exit;
        }

        /* ------------------------------------------------------------------------
         * 4. IMAGEN DESTACADA
         * ------------------------------------------------------------------------ */

        private function copy_featured_image($source_post_id, $target_post_id, $target_lang) {
            $thumbnail_id = get_post_thumbnail_id($source_post_id);
            if ($thumbnail_id) {
                $target_thumb_id = $thumbnail_id;
                
                if (function_exists('pll_get_post')) {
                    $translated_thumb = pll_get_post($thumbnail_id, $target_lang);
                    if ($translated_thumb) {
                        $target_thumb_id = $translated_thumb;
                    }
                }
                
                set_post_thumbnail($target_post_id, $target_thumb_id);
            }
        }

        /* ------------------------------------------------------------------------
         * 5. METADATOS (FICHA TÉCNICA)
         * ------------------------------------------------------------------------ */

        private function copy_post_meta($source_post_id, $target_post_id) {
            $meta_keys = get_post_custom_keys($source_post_id);

            if (empty($meta_keys)) return;

            $ignored_keys = array(
                '_edit_lock',
                '_edit_last',
                '_wp_old_slug',
                '_thumbnail_id',
            );

            foreach ($meta_keys as $key) {
                if (in_array($key, $ignored_keys, true)) continue;

                $meta_values = get_post_custom_values($key, $source_post_id);

                if (!empty($meta_values)) {
                    delete_post_meta($target_post_id, $key);

                    foreach ($meta_values as $value) {
                        $value = maybe_unserialize($value);
                        add_post_meta($target_post_id, $key, $value);
                    }
                }
            }
        }

        /* ------------------------------------------------------------------------
         * 6. TAXONOMÍAS
         * ------------------------------------------------------------------------ */

        private function copy_and_translate_taxonomies($source_post_id, $target_post_id, $source_lang, $target_lang, $api_key, $model) {
            $post_type = get_post_type($source_post_id);
            if (!$post_type) return;

            $taxonomies = get_object_taxonomies($post_type);

            foreach ($taxonomies as $taxonomy) {
                if (in_array($taxonomy, ['language', 'post_translations'], true)) continue;

                $terms = wp_get_object_terms($source_post_id, $taxonomy);
                if (is_wp_error($terms) || empty($terms)) continue;

                $target_term_ids = [];

                foreach ($terms as $term) {
                    $translated_term_id = function_exists('pll_get_term') ? pll_get_term($term->term_id, $target_lang) : false;

                    if ($translated_term_id) {
                        $target_term_ids[] = (int) $translated_term_id;
                    } else {
                        $translated_name = $this->translate_with_gemini($term->name, $source_lang, $target_lang, $api_key, $model, false);
                        
                        if ($translated_name === false || empty($translated_name)) {
                            error_log("Gemini Translator: Se omitió la taxonomía '{$term->name}' al no obtener respuesta de traducción.");
                            continue;
                        }

                        $new_term_args = [];
                        if ($term->parent > 0 && function_exists('pll_get_term')) {
                            $parent_translated_id = pll_get_term($term->parent, $target_lang);
                            if ($parent_translated_id) {
                                $new_term_args['parent'] = $parent_translated_id;
                            }
                        }

                        $new_term = wp_insert_term($translated_name, $taxonomy, $new_term_args);

                        if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                            $new_term_id = (int) $new_term['term_id'];

                            if (function_exists('pll_set_term_language')) {
                                pll_set_term_language($new_term_id, $target_lang);
                            }

                            if (function_exists('pll_save_term_translations')) {
                                $term_translations = function_exists('pll_get_term_translations') ? pll_get_term_translations($term->term_id) : [];
                                if (!is_array($term_translations)) $term_translations = [$source_lang => $term->term_id];
                                $term_translations[$target_lang] = $new_term_id;
                                pll_save_term_translations($term_translations);
                            }

                            $target_term_ids[] = $new_term_id;
                        } elseif (is_wp_error($new_term)) {
                            $existing_term_id = $new_term->get_error_data('term_exists');
                            if ($existing_term_id) {
                                $existing_lang = function_exists('pll_get_term_language') ? pll_get_term_language($existing_term_id) : '';

                                if (empty($existing_lang)) {
                                    if (function_exists('pll_set_term_language')) {
                                        pll_set_term_language($existing_term_id, $target_lang);
                                    }
                                    $target_term_ids[] = (int) $existing_term_id;
                                } elseif ($existing_lang === $target_lang) {
                                    $target_term_ids[] = (int) $existing_term_id;
                                }
                            }
                        }
                    }
                }

                if (!empty($target_term_ids)) {
                    wp_set_object_terms($target_post_id, $target_term_ids, $taxonomy);
                }
            }
        }

        /* ------------------------------------------------------------------------
         * 7. LLAMADA API A GEMINI
         * ------------------------------------------------------------------------ */

        private function translate_with_gemini($text, $from_lang, $to_lang, $api_key, $model = 'gemini-3.5-flash-lite', $is_html = false) {
            if (empty(trim($text))) return '';

            $endpoint = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                sanitize_text_field($model)
            );

            $prompt = "Translate the following text from language code '{$from_lang}' to language code '{$to_lang}'.\n";
            if ($is_html) {
                $prompt .= "CRITICAL INSTRUCTIONS: Preserve all HTML tags, shortcodes, Gutenberg block comment structure (<!-- wp:... -->), attributes, and inline formatting exactly as they are. Translate ONLY the readable text contents inside.\n";
            }
            $prompt .= "Return ONLY the translated text without any conversational preamble, markdown code blocks, or extra explanations.\n\nText to translate:\n" . $text;

            $body = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                ]
            ];

            $response = wp_remote_post($endpoint, [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => sanitize_text_field($api_key),
                ],
                'body'    => json_encode($body, JSON_UNESCAPED_UNICODE),
                'timeout' => 45,
            ]);

            if (is_wp_error($response)) {
                error_log('Gemini Translator Error (WP_Error): ' . $response->get_error_message());
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($code !== 200) {
                error_log(sprintf('Gemini API Error (HTTP %d): %s', $code, $response_body));
                return false;
            }

            $data = json_decode($response_body, true);

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $translated = $data['candidates'][0]['content']['parts'][0]['text'];
                $translated = preg_replace('/^```(?:html)?\s*/i', '', $translated);$translated = preg_replace('/\s*```$/', '', $translated);
                return trim($translated);
            }

            return false;
        }
    }

    add_action('plugins_loaded', function() {
        if (!function_exists('pll_get_post_language')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Gemini Polylang Translator:</strong> Se requiere tener el plugin <strong>Polylang</strong> instalado y activo.</p></div>';
            });
            return;
        }

        new Gemini_Polylang_Translator();
    });
}
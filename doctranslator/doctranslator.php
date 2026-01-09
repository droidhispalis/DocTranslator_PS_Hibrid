<?php
/**
 * DocTranslator - Módulo de Traducción de Documentos para PrestaShop 9
 * 
 * Solución híbrida:
 * - Modo API: Usa LibreTranslate (funciona en cualquier hosting)
 * - Modo Local: Usa servidor Python propio (para VPS, sin límites)
 *
 * @author      DocTranslator
 * @copyright   2024
 * @license     MIT
 * @version     1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class DocTranslator extends Module
{
    const MODE_API = 'api';
    const MODE_LOCAL = 'local';
    
    const LIBRE_TRANSLATE_MIRRORS = [
        'https://libretranslate.com',
        'https://translate.argosopentech.com',
        'https://translate.terraprint.co',
    ];

    public function __construct()
    {
        $this->name = 'doctranslator';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'DocTranslator';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DocTranslator');
        $this->description = $this->l('Traduce documentos PDF, DOCX, TXT. Solución híbrida: API gratuita o servidor local.');
        $this->confirmUninstall = $this->l('¿Eliminar el módulo y todos los documentos traducidos?');
    }

    /**
     * Instalación del módulo
     */
    public function install()
    {
        // Crear tabla para historial de traducciones
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doctranslator_translations` (
            `id_translation` INT(11) NOT NULL AUTO_INCREMENT,
            `id_employee` INT(11) DEFAULT NULL,
            `id_customer` INT(11) DEFAULT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `translated_filename` VARCHAR(255) DEFAULT NULL,
            `source_lang` VARCHAR(5) NOT NULL,
            `target_lang` VARCHAR(5) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT "pending",
            `file_size` INT(11) DEFAULT 0,
            `char_count` INT(11) DEFAULT 0,
            `error_message` TEXT DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_translation`),
            KEY `id_employee` (`id_employee`),
            KEY `id_customer` (`id_customer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        // Crear directorios
        $dirs = [
            _PS_MODULE_DIR_ . $this->name . '/uploads/original',
            _PS_MODULE_DIR_ . $this->name . '/uploads/translated',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Proteger directorios
            file_put_contents($dir . '/.htaccess', 'Deny from all');
            file_put_contents($dir . '/index.php', '<?php header("Location: /"); exit;');
        }

        // Configuración por defecto
        Configuration::updateValue('DOCTRANSLATOR_MODE', self::MODE_API);
        Configuration::updateValue('DOCTRANSLATOR_API_URL', self::LIBRE_TRANSLATE_MIRRORS[0]);
        Configuration::updateValue('DOCTRANSLATOR_API_KEY', '');
        Configuration::updateValue('DOCTRANSLATOR_LOCAL_URL', 'http://127.0.0.1:5000');
        Configuration::updateValue('DOCTRANSLATOR_MAX_SIZE', 10);
        Configuration::updateValue('DOCTRANSLATOR_DAILY_LIMIT', 50);

        return parent::install()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayCustomerAccount')
            && $this->installTab();
    }

    /**
     * Desinstalación
     */
    public function uninstall()
    {
        // Eliminar tabla
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'doctranslator_translations`');

        // Eliminar configuración
        Configuration::deleteByName('DOCTRANSLATOR_MODE');
        Configuration::deleteByName('DOCTRANSLATOR_API_URL');
        Configuration::deleteByName('DOCTRANSLATOR_API_KEY');
        Configuration::deleteByName('DOCTRANSLATOR_LOCAL_URL');
        Configuration::deleteByName('DOCTRANSLATOR_MAX_SIZE');
        Configuration::deleteByName('DOCTRANSLATOR_DAILY_LIMIT');

        return parent::uninstall() && $this->uninstallTab();
    }

    /**
     * Instala tab en el menú
     */
    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminDocTranslator';
        $tab->name = [];
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'DocTranslator';
        }
        
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        $tab->module = $this->name;
        
        return $tab->add();
    }

    /**
     * Desinstala tab
     */
    private function uninstallTab()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminDocTranslator');
        if ($tabId) {
            $tab = new Tab($tabId);
            return $tab->delete();
        }
        return true;
    }

    /**
     * Página de configuración
     */
    public function getContent()
    {
        $output = '';

        // Test de conexión
        if (Tools::isSubmit('testConnection')) {
            $result = $this->testConnection();
            if ($result['success']) {
                $output .= $this->displayConfirmation(
                    $this->l('Conexión exitosa.') . ' ' . 
                    $this->l('Idiomas disponibles: ') . implode(', ', $result['languages'])
                );
            } else {
                $output .= $this->displayError($this->l('Error de conexión: ') . $result['error']);
            }
        }

        // Guardar configuración
        if (Tools::isSubmit('submitDocTranslatorConfig')) {
            Configuration::updateValue('DOCTRANSLATOR_MODE', Tools::getValue('DOCTRANSLATOR_MODE'));
            Configuration::updateValue('DOCTRANSLATOR_API_URL', Tools::getValue('DOCTRANSLATOR_API_URL'));
            Configuration::updateValue('DOCTRANSLATOR_API_KEY', Tools::getValue('DOCTRANSLATOR_API_KEY'));
            Configuration::updateValue('DOCTRANSLATOR_LOCAL_URL', Tools::getValue('DOCTRANSLATOR_LOCAL_URL'));
            Configuration::updateValue('DOCTRANSLATOR_MAX_SIZE', (int) Tools::getValue('DOCTRANSLATOR_MAX_SIZE'));
            Configuration::updateValue('DOCTRANSLATOR_DAILY_LIMIT', (int) Tools::getValue('DOCTRANSLATOR_DAILY_LIMIT'));
            
            $output .= $this->displayConfirmation($this->l('Configuración guardada.'));
        }

        return $output . $this->renderConfigForm();
    }

    /**
     * Formulario de configuración
     */
    protected function renderConfigForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuración de DocTranslator'),
                    'icon' => 'icon-cogs'
                ],
                'tabs' => [
                    'general' => $this->l('General'),
                    'api' => $this->l('Modo API (Recomendado)'),
                    'local' => $this->l('Modo Local (VPS)'),
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Modo de traducción'),
                        'name' => 'DOCTRANSLATOR_MODE',
                        'tab' => 'general',
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'mode_api',
                                'value' => self::MODE_API,
                                'label' => $this->l('API Externa (LibreTranslate) - Funciona en cualquier hosting')
                            ],
                            [
                                'id' => 'mode_local',
                                'value' => self::MODE_LOCAL,
                                'label' => $this->l('Servidor Local (Python) - Para VPS, sin límites')
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tamaño máximo de archivo (MB)'),
                        'name' => 'DOCTRANSLATOR_MAX_SIZE',
                        'tab' => 'general',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Tamaño máximo permitido para documentos'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Límite diario de traducciones'),
                        'name' => 'DOCTRANSLATOR_DAILY_LIMIT',
                        'tab' => 'general',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('0 = sin límite (solo modo local)'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Servidor LibreTranslate'),
                        'name' => 'DOCTRANSLATOR_API_URL',
                        'tab' => 'api',
                        'options' => [
                            'query' => array_map(function($url) {
                                return ['id' => $url, 'name' => $url];
                            }, self::LIBRE_TRANSLATE_MIRRORS),
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Selecciona un servidor público o introduce uno propio'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API Key (opcional)'),
                        'name' => 'DOCTRANSLATOR_API_KEY',
                        'tab' => 'api',
                        'desc' => $this->l('Algunos servidores requieren API key'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL del servidor Python'),
                        'name' => 'DOCTRANSLATOR_LOCAL_URL',
                        'tab' => 'local',
                        'desc' => $this->l('Ejemplo: http://127.0.0.1:5000'),
                    ],
                    [
                        'type' => 'html',
                        'name' => 'local_instructions',
                        'tab' => 'local',
                        'html_content' => $this->getLocalInstructions(),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                ],
                'buttons' => [
                    [
                        'type' => 'submit',
                        'title' => $this->l('Probar conexión'),
                        'name' => 'testConnection',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitDocTranslatorConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) 
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'DOCTRANSLATOR_MODE' => Configuration::get('DOCTRANSLATOR_MODE'),
                'DOCTRANSLATOR_API_URL' => Configuration::get('DOCTRANSLATOR_API_URL'),
                'DOCTRANSLATOR_API_KEY' => Configuration::get('DOCTRANSLATOR_API_KEY'),
                'DOCTRANSLATOR_LOCAL_URL' => Configuration::get('DOCTRANSLATOR_LOCAL_URL'),
                'DOCTRANSLATOR_MAX_SIZE' => Configuration::get('DOCTRANSLATOR_MAX_SIZE'),
                'DOCTRANSLATOR_DAILY_LIMIT' => Configuration::get('DOCTRANSLATOR_DAILY_LIMIT'),
            ],
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Instrucciones para modo local
     */
    private function getLocalInstructions()
    {
        return '
        <div class="alert alert-info">
            <h4>' . $this->l('Instrucciones para Modo Local (VPS)') . '</h4>
            <p>' . $this->l('Para usar el modo local sin límites, necesitas:') . '</p>
            <ol>
                <li>' . $this->l('Un VPS con Python 3.9+ instalado') . '</li>
                <li>' . $this->l('Descargar e instalar el servidor de traducción') . '</li>
                <li>' . $this->l('Ejecutar el servidor en el puerto 5000') . '</li>
            </ol>
            <p><a href="https://github.com/droidhispalis/Translate_IA" target="_blank" class="btn btn-default">
                <i class="icon-github"></i> ' . $this->l('Ver instrucciones en GitHub') . '
            </a></p>
        </div>';
    }

    /**
     * Test de conexión al servicio de traducción
     */
    public function testConnection()
    {
        $mode = Configuration::get('DOCTRANSLATOR_MODE');
        
        if ($mode === self::MODE_LOCAL) {
            return $this->testLocalConnection();
        } else {
            return $this->testApiConnection();
        }
    }

    /**
     * Test conexión API externa
     */
    private function testApiConnection()
    {
        $apiUrl = Configuration::get('DOCTRANSLATOR_API_URL');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($apiUrl, '/') . '/languages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'error' => 'Respuesta inválida'];
        }

        $languages = array_column($data, 'code');
        return ['success' => true, 'languages' => $languages];
    }

    /**
     * Test conexión servidor local
     */
    private function testLocalConnection()
    {
        $localUrl = Configuration::get('DOCTRANSLATOR_LOCAL_URL');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($localUrl, '/') . '/api/v1/status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode . ' - ¿Está el servidor Python ejecutándose?'];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['status'])) {
            return ['success' => false, 'error' => 'Respuesta inválida del servidor'];
        }

        return [
            'success' => true, 
            'languages' => $data['languages'] ?? ['es', 'en', 'fr', 'zh']
        ];
    }

    /**
     * Traduce texto usando el servicio configurado
     */
    public function translateText($text, $sourceLang, $targetLang)
    {
        $mode = Configuration::get('DOCTRANSLATOR_MODE');
        
        if ($mode === self::MODE_LOCAL) {
            return $this->translateLocal($text, $sourceLang, $targetLang);
        } else {
            return $this->translateApi($text, $sourceLang, $targetLang);
        }
    }

    /**
     * Traducción via API externa (LibreTranslate)
     */
    private function translateApi($text, $sourceLang, $targetLang)
    {
        $apiUrl = Configuration::get('DOCTRANSLATOR_API_URL');
        $apiKey = Configuration::get('DOCTRANSLATOR_API_KEY');

        $postData = [
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'format' => 'text',
        ];

        if (!empty($apiKey)) {
            $postData['api_key'] = $apiKey;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($apiUrl, '/') . '/translate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['translatedText'])) {
            return ['success' => false, 'error' => 'Respuesta inválida'];
        }

        return ['success' => true, 'text' => $data['translatedText']];
    }

    /**
     * Traducción via servidor local Python
     */
    private function translateLocal($text, $sourceLang, $targetLang)
    {
        $localUrl = Configuration::get('DOCTRANSLATOR_LOCAL_URL');

        $postData = [
            'text' => $text,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($localUrl, '/') . '/api/v1/translate/text',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['translated']['text'])) {
            return ['success' => false, 'error' => 'Respuesta inválida'];
        }

        return ['success' => true, 'text' => $data['translated']['text']];
    }

    /**
     * Obtiene idiomas disponibles
     */
    public function getAvailableLanguages()
    {
        $mode = Configuration::get('DOCTRANSLATOR_MODE');
        
        // Idiomas soportados
        return [
            'es' => ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸'],
            'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'],
            'fr' => ['code' => 'fr', 'name' => 'Français', 'flag' => '🇫🇷'],
            'de' => ['code' => 'de', 'name' => 'Deutsch', 'flag' => '🇩🇪'],
            'it' => ['code' => 'it', 'name' => 'Italiano', 'flag' => '🇮🇹'],
            'pt' => ['code' => 'pt', 'name' => 'Português', 'flag' => '🇵🇹'],
            'zh' => ['code' => 'zh', 'name' => '中文', 'flag' => '🇨🇳'],
            'ja' => ['code' => 'ja', 'name' => '日本語', 'flag' => '🇯🇵'],
            'ru' => ['code' => 'ru', 'name' => 'Русский', 'flag' => '🇷🇺'],
            'ar' => ['code' => 'ar', 'name' => 'العربية', 'flag' => '🇸🇦'],
        ];
    }

    /**
     * Hook para CSS/JS en backoffice
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') === $this->name || 
            Tools::getValue('controller') === 'AdminDocTranslator') {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        }
    }

    /**
     * Hook para mostrar en cuenta de cliente
     */
    public function hookDisplayCustomerAccount()
    {
        return $this->display(__FILE__, 'views/templates/hook/customer-account.tpl');
    }

    /**
     * Verifica límite diario
     */
    public function checkDailyLimit($userId = null, $isEmployee = true)
    {
        $limit = (int) Configuration::get('DOCTRANSLATOR_DAILY_LIMIT');
        
        if ($limit === 0) {
            return true; // Sin límite
        }

        $field = $isEmployee ? 'id_employee' : 'id_customer';
        
        $count = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'doctranslator_translations`
            WHERE `' . $field . '` = ' . (int) $userId . '
            AND DATE(`date_add`) = CURDATE()
        ');

        return $count < $limit;
    }

    /**
     * Registra una traducción en el historial
     */
    public function logTranslation($data)
    {
        return Db::getInstance()->insert('doctranslator_translations', [
            'id_employee' => isset($data['id_employee']) ? (int) $data['id_employee'] : null,
            'id_customer' => isset($data['id_customer']) ? (int) $data['id_customer'] : null,
            'original_filename' => pSQL($data['original_filename']),
            'translated_filename' => isset($data['translated_filename']) ? pSQL($data['translated_filename']) : null,
            'source_lang' => pSQL($data['source_lang']),
            'target_lang' => pSQL($data['target_lang']),
            'status' => pSQL($data['status'] ?? 'pending'),
            'file_size' => (int) ($data['file_size'] ?? 0),
            'char_count' => (int) ($data['char_count'] ?? 0),
            'error_message' => isset($data['error_message']) ? pSQL($data['error_message']) : null,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Actualiza estado de traducción
     */
    public function updateTranslation($id, $data)
    {
        $data['date_upd'] = date('Y-m-d H:i:s');
        return Db::getInstance()->update('doctranslator_translations', $data, 'id_translation = ' . (int) $id);
    }

    /**
     * Obtiene historial de traducciones
     */
    public function getTranslations($userId = null, $isEmployee = true, $limit = 50)
    {
        $field = $isEmployee ? 'id_employee' : 'id_customer';
        $where = $userId ? 'WHERE `' . $field . '` = ' . (int) $userId : '';
        
        return Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'doctranslator_translations`
            ' . $where . '
            ORDER BY `date_add` DESC
            LIMIT ' . (int) $limit
        );
    }
}

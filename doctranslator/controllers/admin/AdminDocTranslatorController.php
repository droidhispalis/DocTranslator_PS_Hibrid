<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminDocTranslatorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    public function renderView()
    {
        $module = Module::getInstanceByName('doctranslator');

        $languages = $module->getAvailableLanguages();
        $mode = Configuration::get('DOCTRANSLATOR_MODE');
        $maxSize = Configuration::get('DOCTRANSLATOR_MAX_SIZE');
        $dailyLimit = Configuration::get('DOCTRANSLATOR_DAILY_LIMIT');

        $todayCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'doctranslator_translations` 
            WHERE `id_employee` = ' . (int) $this->context->employee->id . ' 
            AND DATE(`date_add`) = CURDATE()'
        );

        $translations = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'doctranslator_translations` 
            WHERE `id_employee` = ' . (int) $this->context->employee->id . ' 
            ORDER BY date_add DESC LIMIT 20'
        );

        $this->context->smarty->assign([
            'languages' => $languages,
            'translations' => $translations ? $translations : [],
            'mode' => $mode,
            'mode_label' => $mode === 'api' ? 'API Externa' : 'Servidor Local',
            'max_size' => $maxSize,
            'daily_limit' => $dailyLimit,
            'today_count' => $todayCount,
            'can_translate' => true,
            'ajax_url' => $this->context->link->getAdminLink('AdminDocTranslator'),
            'module_dir' => _MODULE_DIR_ . 'doctranslator/',
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'doctranslator/views/templates/admin/translator.tpl');
    }

    public function ajaxProcessTranslate()
    {
        $response = ['success' => false];

        try {
            $module = Module::getInstanceByName('doctranslator');

            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir el archivo.');
            }

            $file = $_FILES['document'];
            $sourceLang = Tools::getValue('source_lang');
            $targetLang = Tools::getValue('target_lang');

            if ($sourceLang === $targetLang) {
                throw new Exception('Los idiomas deben ser diferentes.');
            }

            $allowedExt = ['pdf', 'docx', 'doc', 'txt', 'odt'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                throw new Exception('Tipo de archivo no permitido.');
            }

            $uploadDir = _PS_MODULE_DIR_ . 'doctranslator/uploads/original/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $uploadPath = $uploadDir . $uniqueName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al guardar el archivo.');
            }

            // Registrar en BD
            Db::getInstance()->insert('doctranslator_translations', [
                'id_employee' => (int) $this->context->employee->id,
                'original_filename' => pSQL($file['name']),
                'source_lang' => pSQL($sourceLang),
                'target_lang' => pSQL($targetLang),
                'file_size' => (int) $file['size'],
                'status' => 'processing',
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s'),
            ]);

            $translationId = Db::getInstance()->Insert_ID();

            // Procesar traduccion
            require_once _PS_MODULE_DIR_ . 'doctranslator/classes/DocTranslatorProcessor.php';
            $processor = new DocTranslatorProcessor($module);
            $result = $processor->processDocument($uploadPath, $sourceLang, $targetLang);

            if ($result['success']) {
                $translatedDir = _PS_MODULE_DIR_ . 'doctranslator/uploads/translated/';
                if (!is_dir($translatedDir)) {
                    mkdir($translatedDir, 0755, true);
                }

                $translatedName = pathinfo($uniqueName, PATHINFO_FILENAME) . '_' . $targetLang . '.' . $ext;
                file_put_contents($translatedDir . $translatedName, $result['content']);

                Db::getInstance()->update('doctranslator_translations', [
                    'translated_filename' => pSQL($translatedName),
                    'char_count' => (int) ($result['char_count'] ?? 0),
                    'status' => 'completed',
                    'date_upd' => date('Y-m-d H:i:s'),
                ], 'id_translation = ' . (int) $translationId);

                $response = [
                    'success' => true,
                    'download_url' => $this->context->link->getAdminLink('AdminDocTranslator') . '&action=download&file=' . urlencode($translatedName),
                    'char_count' => $result['char_count'] ?? 0,
                ];
            } else {
                Db::getInstance()->update('doctranslator_translations', [
                    'status' => 'error',
                    'error_message' => pSQL($result['error']),
                    'date_upd' => date('Y-m-d H:i:s'),
                ], 'id_translation = ' . (int) $translationId);

                throw new Exception($result['error']);
            }

        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
        }

        header('Content-Type: application/json');
        die(json_encode($response));
    }

    public function processDownload()
    {
        $filename = basename(Tools::getValue('file'));
        $filepath = _PS_MODULE_DIR_ . 'doctranslator/uploads/translated/' . $filename;

        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
        }
        exit;
    }
}
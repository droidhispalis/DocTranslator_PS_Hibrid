<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class DocTranslatorProcessor
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function processDocument($filePath, $sourceLang, $targetLang)
    {
        $mode = Configuration::get('DOCTRANSLATOR_MODE');

        if ($mode === 'local') {
            return $this->processWithLocalServer($filePath, $sourceLang, $targetLang);
        } else {
            return $this->processWithApi($filePath, $sourceLang, $targetLang);
        }
    }

    private function processWithLocalServer($filePath, $sourceLang, $targetLang)
    {
        $localUrl = Configuration::get('DOCTRANSLATOR_LOCAL_URL');

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }

        $ch = curl_init();

        $postFields = [
            'file' => new CURLFile($filePath),
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($localUrl, '/') . '/api/v1/translate',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Error de conexion: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Error HTTP: ' . $httpCode];
        }

        $data = json_decode($response, true);

        if (!$data) {
            return ['success' => false, 'error' => 'Respuesta invalida del servidor'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']];
        }

        if (isset($data['translated_file'])) {
            $fileContent = base64_decode($data['translated_file']);
            return [
                'success' => true,
                'content' => $fileContent,
                'char_count' => $data['char_count'] ?? 0,
            ];
        }

        if (isset($data['translated']['text'])) {
            return [
                'success' => true,
                'content' => $data['translated']['text'],
                'char_count' => strlen($data['translated']['text']),
            ];
        }

        return ['success' => false, 'error' => 'Formato de respuesta no reconocido'];
    }

    private function processWithApi($filePath, $sourceLang, $targetLang)
    {
        $apiUrl = Configuration::get('DOCTRANSLATOR_API_URL');

        $text = $this->extractText($filePath);

        if (empty($text)) {
            return ['success' => false, 'error' => 'No se pudo extraer texto del documento'];
        }

        $chunks = $this->splitText($text, 5000);
        $translatedChunks = [];

        foreach ($chunks as $chunk) {
            $result = $this->translateWithApi($chunk, $sourceLang, $targetLang, $apiUrl);
            if (!$result['success']) {
                return $result;
            }
            $translatedChunks[] = $result['text'];
        }

        $translatedText = implode("\n\n", $translatedChunks);

        return [
            'success' => true,
            'content' => $translatedText,
            'char_count' => strlen($text),
        ];
    }

    private function translateWithApi($text, $sourceLang, $targetLang, $apiUrl)
    {
        $ch = curl_init();

        $postData = json_encode([
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'format' => 'text',
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($apiUrl, '/') . '/translate',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $data = json_decode($response, true);

        if (isset($data['translatedText'])) {
            return ['success' => true, 'text' => $data['translatedText']];
        }

        return ['success' => false, 'error' => 'Error en traduccion'];
    }

    private function extractText($filePath)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'txt':
                return file_get_contents($filePath);
            case 'docx':
                return $this->extractFromDocx($filePath);
            default:
                return '';
        }
    }

    private function extractFromDocx($filePath)
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$content) {
            return '';
        }

        $text = strip_tags(str_replace('<', ' <', $content));
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function splitText($text, $maxLength)
    {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $current = '';

        foreach ($paragraphs as $para) {
            if (strlen($current) + strlen($para) > $maxLength) {
                if ($current) {
                    $chunks[] = trim($current);
                }
                $current = $para;
            } else {
                $current .= "\n\n" . $para;
            }
        }

        if ($current) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
/**
 * DocTranslator - JavaScript Admin
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elementos
    var uploadZone = document.getElementById('upload-zone');
    var fileInput = document.getElementById('document-input');
    var uploadPlaceholder = document.getElementById('upload-placeholder');
    var fileInfo = document.getElementById('file-info');
    var fileName = document.getElementById('file-name');
    var fileSize = document.getElementById('file-size');
    var removeFile = document.getElementById('remove-file');
    var translateBtn = document.getElementById('translate-btn');
    var translateForm = document.getElementById('translate-form');
    var swapLangs = document.getElementById('swap-langs');
    var sourceLang = document.getElementById('source_lang');
    var targetLang = document.getElementById('target_lang');
    var progressSection = document.getElementById('progress-section');
    var progressBar = document.getElementById('progress-bar');
    var progressText = document.getElementById('progress-text');
    var resultSection = document.getElementById('result-section');
    var resultInfo = document.getElementById('result-info');
    var downloadBtn = document.getElementById('download-btn');
    var newTranslation = document.getElementById('new-translation');
    var errorSection = document.getElementById('error-section');
    var errorMessage = document.getElementById('error-message');
    var retryBtn = document.getElementById('retry-btn');

    var selectedFile = null;

    // Formatear tamaño de archivo
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Mostrar archivo seleccionado
    function showFile(file) {
        selectedFile = file;
        fileName.textContent = file.name;
        fileSize.textContent = '(' + formatFileSize(file.size) + ')';
        uploadPlaceholder.style.display = 'none';
        fileInfo.style.display = 'flex';
        uploadZone.classList.add('has-file');
        translateBtn.disabled = false;
    }

    // Ocultar archivo
    function hideFile() {
        selectedFile = null;
        fileInput.value = '';
        uploadPlaceholder.style.display = 'block';
        fileInfo.style.display = 'none';
        uploadZone.classList.remove('has-file');
        translateBtn.disabled = true;
    }

    // Reset de la interfaz
    function resetUI() {
        hideFile();
        progressSection.style.display = 'none';
        resultSection.style.display = 'none';
        errorSection.style.display = 'none';
        progressBar.style.width = '0%';
    }

    // Click en zona de upload
    if (uploadZone) {
        uploadZone.addEventListener('click', function(e) {
            if (e.target !== removeFile && !removeFile.contains(e.target)) {
                fileInput.click();
            }
        });
    }

    // Cambio de archivo
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                showFile(e.target.files[0]);
            }
        });
    }

    // Drag and drop
    if (uploadZone) {
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                showFile(e.dataTransfer.files[0]);
            }
        });
    }

    // Eliminar archivo
    if (removeFile) {
        removeFile.addEventListener('click', function(e) {
            e.stopPropagation();
            hideFile();
        });
    }

    // Intercambiar idiomas
    if (swapLangs) {
        swapLangs.addEventListener('click', function() {
            var temp = sourceLang.value;
            sourceLang.value = targetLang.value;
            targetLang.value = temp;
        });
    }

    // Enviar formulario
    if (translateForm) {
        translateForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!selectedFile) {
                alert('Por favor selecciona un archivo');
                return;
            }

            if (sourceLang.value === targetLang.value) {
                alert('Los idiomas de origen y destino deben ser diferentes');
                return;
            }

            // Preparar FormData
            var formData = new FormData();
            formData.append('document', selectedFile);
            formData.append('source_lang', sourceLang.value);
            formData.append('target_lang', targetLang.value);
            formData.append('ajax', '1');
            formData.append('action', 'translate');

            // Mostrar progreso
            translateBtn.disabled = true;
            progressSection.style.display = 'block';
            resultSection.style.display = 'none';
            errorSection.style.display = 'none';

            // Simular progreso
            var progress = 0;
            var progressSteps = [
                { pct: 20, text: 'Subiendo documento...' },
                { pct: 40, text: 'Extrayendo texto...' },
                { pct: 60, text: 'Traduciendo...' },
                { pct: 80, text: 'Generando documento...' },
                { pct: 95, text: 'Finalizando...' }
            ];
            var stepIndex = 0;

            var progressInterval = setInterval(function() {
                if (stepIndex < progressSteps.length) {
                    progressBar.style.width = progressSteps[stepIndex].pct + '%';
                    progressText.textContent = progressSteps[stepIndex].text;
                    stepIndex++;
                }
            }, 1500);

            // Enviar petición
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';

                if (data.success) {
                    progressText.textContent = '¡Completado!';
                    
                    setTimeout(function() {
                        progressSection.style.display = 'none';
                        resultSection.style.display = 'block';
                        resultInfo.textContent = data.char_count ? 
                            'Se tradujeron ' + data.char_count.toLocaleString() + ' caracteres.' : 
                            'Documento traducido correctamente.';
                        downloadBtn.href = data.download_url;
                        translateBtn.disabled = false;
                    }, 500);
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(function(error) {
                clearInterval(progressInterval);
                progressSection.style.display = 'none';
                errorSection.style.display = 'block';
                errorMessage.textContent = error.message;
                translateBtn.disabled = false;
            });
        });
    }

    // Nueva traducción
    if (newTranslation) {
        newTranslation.addEventListener('click', resetUI);
    }

    // Reintentar
    if (retryBtn) {
        retryBtn.addEventListener('click', function() {
            errorSection.style.display = 'none';
            if (selectedFile) {
                translateForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    // Eliminar traducciones del historial
    document.querySelectorAll('.delete-translation').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('¿Eliminar esta traducción?')) return;

            var id = this.getAttribute('data-id');
            var row = this.closest('tr');

            var formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    row.remove();
                } else {
                    alert(data.error || 'Error al eliminar');
                }
            })
            .catch(function(error) {
                alert('Error de conexión');
            });
        });
    });
});

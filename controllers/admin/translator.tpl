<div class="panel">
    <div class="panel-heading">
        <i class="icon-language"></i> Traducir Documento
        <span class="badge badge-info">{$mode_label}</span>
    </div>
    
    <div class="panel-body">
        <form id="translate-form" enctype="multipart/form-data" class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-lg-3">Idioma origen</label>
                <div class="col-lg-4">
                    <select name="source_lang" id="source_lang" class="form-control">
                        {foreach from=$languages key=code item=lang}
                            <option value="{$code}" {if $code == 'es'}selected{/if}>{$lang.name}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">Idioma destino</label>
                <div class="col-lg-4">
                    <select name="target_lang" id="target_lang" class="form-control">
                        {foreach from=$languages key=code item=lang}
                            <option value="{$code}" {if $code == 'en'}selected{/if}>{$lang.name}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">Documento</label>
                <div class="col-lg-4">
                    <input type="file" name="document" id="document" class="form-control" accept=".pdf,.docx,.doc,.txt,.odt" required>
                    <p class="help-block">PDF, DOCX, DOC, TXT, ODT - Max {$max_size}MB</p>
                </div>
            </div>

            <div class="form-group">
                <div class="col-lg-offset-3 col-lg-4">
                    <button type="submit" id="translate-btn" class="btn btn-primary">
                        <i class="icon-language"></i> Traducir
                    </button>
                </div>
            </div>
        </form>

        <div id="progress-section" style="display:none;" class="alert alert-info">
            <i class="icon-refresh icon-spin"></i> Traduciendo documento...
        </div>

        <div id="result-section" style="display:none;" class="alert alert-success">
            <h4><i class="icon-check"></i> Traduccion completada</h4>
            <p id="result-info"></p>
            <a href="#" id="download-btn" class="btn btn-success">
                <i class="icon-download"></i> Descargar
            </a>
        </div>

        <div id="error-section" style="display:none;" class="alert alert-danger">
            <h4><i class="icon-warning"></i> Error</h4>
            <p id="error-message"></p>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-history"></i> Historial ({$today_count} hoy)
    </div>
    <div class="panel-body">
        {if $translations|@count > 0}
            <table class="table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Idiomas</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$translations item=t}
                        <tr>
                            <td>{$t.original_filename|truncate:30}</td>
                            <td>{$t.source_lang} → {$t.target_lang}</td>
                            <td>
                                {if $t.status == 'completed'}
                                    <span class="label label-success">OK</span>
                                {elseif $t.status == 'error'}
                                    <span class="label label-danger">Error</span>
                                {else}
                                    <span class="label label-warning">...</span>
                                {/if}
                            </td>
                            <td>{$t.date_add}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <p class="alert alert-info">Sin traducciones todavia.</p>
        {/if}
    </div>
</div>

<script>
var ajaxUrl = '{$ajax_url}';

document.getElementById('translate-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData();
    formData.append('document', document.getElementById('document').files[0]);
    formData.append('source_lang', document.getElementById('source_lang').value);
    formData.append('target_lang', document.getElementById('target_lang').value);
    formData.append('ajax', '1');
    formData.append('action', 'translate');

    document.getElementById('progress-section').style.display = 'block';
    document.getElementById('result-section').style.display = 'none';
    document.getElementById('error-section').style.display = 'none';
    document.getElementById('translate-btn').disabled = true;

    fetch(ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        document.getElementById('progress-section').style.display = 'none';
        document.getElementById('translate-btn').disabled = false;
        
        if (data.success) {
            document.getElementById('result-section').style.display = 'block';
            document.getElementById('result-info').textContent = 'Caracteres: ' + (data.char_count || 0);
            document.getElementById('download-btn').href = data.download_url;
        } else {
            document.getElementById('error-section').style.display = 'block';
            document.getElementById('error-message').textContent = data.error;
        }
    })
    .catch(function(error) {
        document.getElementById('progress-section').style.display = 'none';
        document.getElementById('translate-btn').disabled = false;
        document.getElementById('error-section').style.display = 'block';
        document.getElementById('error-message').textContent = 'Error de conexion';
    });
});
</script>
{**
 * DocTranslator - Interfaz de Traducción (Admin)
 *}

<div class="panel" id="doctranslator-panel">
    <div class="panel-heading">
        <i class="icon-language"></i> {l s='Traducir Documento' mod='doctranslator'}
        <span class="badge badge-info">{$mode_label}</span>
    </div>
    
    <div class="panel-body">
        {if !$can_translate}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                {l s='Has alcanzado el límite diario de traducciones.' mod='doctranslator'}
                ({$today_count}/{$daily_limit})
            </div>
        {else}
            {* Formulario de traducción *}
            <form id="translate-form" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="source_lang">{l s='Idioma origen' mod='doctranslator'}</label>
                            <select name="source_lang" id="source_lang" class="form-control">
                                {foreach from=$languages key=code item=lang}
                                    <option value="{$code}" {if $code == 'es'}selected{/if}>
                                        {$lang.flag} {$lang.name}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-2 text-center">
                        <button type="button" id="swap-langs" class="btn btn-default" style="margin-top: 25px;">
                            <i class="icon-exchange"></i>
                        </button>
                    </div>
                    
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="target_lang">{l s='Idioma destino' mod='doctranslator'}</label>
                            <select name="target_lang" id="target_lang" class="form-control">
                                {foreach from=$languages key=code item=lang}
                                    <option value="{$code}" {if $code == 'en'}selected{/if}>
                                        {$lang.flag} {$lang.name}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>{l s='Documento a traducir' mod='doctranslator'}</label>
                    <div id="upload-zone" class="upload-zone">
                        <input type="file" name="document" id="document-input" 
                               accept=".pdf,.docx,.doc,.txt,.odt" style="display:none;">
                        <div id="upload-placeholder">
                            <i class="icon-cloud-upload"></i>
                            <p>{l s='Arrastra un archivo o haz clic para seleccionar' mod='doctranslator'}</p>
                            <small>PDF, DOCX, DOC, TXT, ODT - {l s='Máximo' mod='doctranslator'} {$max_size}MB</small>
                        </div>
                        <div id="file-info" style="display:none;">
                            <i class="icon-file-text"></i>
                            <span id="file-name"></span>
                            <span id="file-size"></span>
                            <button type="button" id="remove-file" class="btn btn-link text-danger">
                                <i class="icon-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" id="translate-btn" class="btn btn-primary btn-lg" disabled>
                        <i class="icon-language"></i> {l s='Traducir documento' mod='doctranslator'}
                    </button>
                </div>
            </form>

            {* Progreso *}
            <div id="progress-section" style="display:none;">
                <div class="progress">
                    <div id="progress-bar" class="progress-bar progress-bar-striped active" style="width:0%"></div>
                </div>
                <p id="progress-text" class="text-center text-muted">{l s='Procesando...' mod='doctranslator'}</p>
            </div>

            {* Resultado *}
            <div id="result-section" class="alert alert-success" style="display:none;">
                <h4><i class="icon-check"></i> {l s='¡Traducción completada!' mod='doctranslator'}</h4>
                <p id="result-info"></p>
                <a href="#" id="download-btn" class="btn btn-success">
                    <i class="icon-download"></i> {l s='Descargar documento traducido' mod='doctranslator'}
                </a>
                <button type="button" id="new-translation" class="btn btn-default">
                    {l s='Nueva traducción' mod='doctranslator'}
                </button>
            </div>

            {* Error *}
            <div id="error-section" class="alert alert-danger" style="display:none;">
                <h4><i class="icon-warning"></i> {l s='Error' mod='doctranslator'}</h4>
                <p id="error-message"></p>
                <button type="button" id="retry-btn" class="btn btn-danger">
                    {l s='Intentar de nuevo' mod='doctranslator'}
                </button>
            </div>
        {/if}

        {* Contador de traducciones *}
        <div class="text-right text-muted" style="margin-top: 15px;">
            {l s='Traducciones hoy:' mod='doctranslator'} {$today_count}
            {if $daily_limit > 0} / {$daily_limit}{/if}
        </div>
    </div>
</div>

{* Historial de traducciones *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-history"></i> {l s='Historial de Traducciones' mod='doctranslator'}
    </div>
    
    <div class="panel-body">
        {if $translations|@count > 0}
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>{l s='Archivo' mod='doctranslator'}</th>
                        <th>{l s='Idiomas' mod='doctranslator'}</th>
                        <th>{l s='Estado' mod='doctranslator'}</th>
                        <th>{l s='Fecha' mod='doctranslator'}</th>
                        <th>{l s='Acciones' mod='doctranslator'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$translations item=t}
                        <tr data-id="{$t.id_translation}">
                            <td>
                                <i class="icon-file-text"></i>
                                {$t.original_filename|truncate:40:'...'}
                                {if $t.char_count > 0}
                                    <br><small class="text-muted">{$t.char_count|number_format:0:',':'.'} {l s='caracteres' mod='doctranslator'}</small>
                                {/if}
                            </td>
                            <td>
                                <span class="badge">{$t.source_lang|upper}</span>
                                <i class="icon-arrow-right"></i>
                                <span class="badge">{$t.target_lang|upper}</span>
                            </td>
                            <td>
                                {if $t.status == 'completed'}
                                    <span class="label label-success">{l s='Completado' mod='doctranslator'}</span>
                                {elseif $t.status == 'processing'}
                                    <span class="label label-warning">{l s='Procesando' mod='doctranslator'}</span>
                                {elseif $t.status == 'error'}
                                    <span class="label label-danger" title="{$t.error_message}">{l s='Error' mod='doctranslator'}</span>
                                {else}
                                    <span class="label label-default">{l s='Pendiente' mod='doctranslator'}</span>
                                {/if}
                            </td>
                            <td>{$t.date_add|date_format:'%d/%m/%Y %H:%M'}</td>
                            <td>
                                {if $t.status == 'completed' && $t.translated_filename}
                                    <a href="{$ajax_url}&action=download&file={$t.translated_filename|urlencode}" 
                                       class="btn btn-default btn-sm" title="{l s='Descargar' mod='doctranslator'}">
                                        <i class="icon-download"></i>
                                    </a>
                                {/if}
                                <button type="button" class="btn btn-danger btn-sm delete-translation" 
                                        data-id="{$t.id_translation}" title="{l s='Eliminar' mod='doctranslator'}">
                                    <i class="icon-trash"></i>
                                </button>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i>
                {l s='Aún no has realizado ninguna traducción.' mod='doctranslator'}
            </div>
        {/if}
    </div>
</div>

{* Información del modo *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-info-circle"></i> {l s='Información' mod='doctranslator'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-4">
                <h5><i class="icon-globe"></i> {l s='Modo actual' mod='doctranslator'}</h5>
                <p>
                    {if $mode == 'api'}
                        <span class="label label-info">{l s='API Externa' mod='doctranslator'}</span>
                        <br><small>{l s='Usando servidor LibreTranslate' mod='doctranslator'}</small>
                    {else}
                        <span class="label label-success">{l s='Servidor Local' mod='doctranslator'}</span>
                        <br><small>{l s='Sin límites de traducción' mod='doctranslator'}</small>
                    {/if}
                </p>
            </div>
            <div class="col-md-4">
                <h5><i class="icon-file-text"></i> {l s='Formatos soportados' mod='doctranslator'}</h5>
                <p>PDF, DOCX, DOC, TXT, ODT</p>
            </div>
            <div class="col-md-4">
                <h5><i class="icon-language"></i> {l s='Idiomas' mod='doctranslator'}</h5>
                <p>{l s='Español, Inglés, Francés, Alemán, Italiano, Portugués, Chino, Japonés, Ruso, Árabe' mod='doctranslator'}</p>
            </div>
        </div>
    </div>
</div>

<script>
var ajaxUrl = '{$ajax_url|escape:'javascript'}';
</script>

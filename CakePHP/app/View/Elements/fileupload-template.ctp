<!-- The template to display files available for upload -->
<script id="template-upload" type="text/x-tmpl">
	{% for (var i=0, file; file=o.files[i]; i++) { %}
	<tr class="template-upload fade">
		<td></td>
		<td class="name"><span>{%=file.name%}</span></td>
		<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
		{% if (file.error) { %}
		<td class="error" colspan="2"><span class="label label-important">Erreur</span> {%=file.error%}</td>
		{% } else if (o.files.valid && !i) { %}
		<td>
			<div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="bar" style="width:0%;"></div></div>
		</td>
		<td style="text-align:right;">{% if (!o.options.autoUpload) { %}
			<button class="btn btn-primary start">
			<i class="icon-upload icon-white"></i>
			<span>Envoyer</span>
			</button>
		{% } %}</td>
		{% } else { %}
		<td colspan="2"></td>
		{% } %}
		<td class="action" style="text-align:right">{% if (!i) { %}
			<button class="btn btn-warning cancel bouton-annuler">
			<i class="icon-ban-circle icon-white"></i>
			<span>Annuler</span>
			</button>
		{% } %}</td>
	</tr>
	{% } %}
</script>
<!-- The template to display files available for download -->
<script id="template-download" type="text/x-tmpl">
	{% for (var i=0, file; file=o.files[i]; i++) { %}
	<tr class="template-download fade">
		<td>
		{% if (!file.error) { %}
		<input type="checkbox"  {% if (!file.delete_ckb) { %} style="display:none" {% } %} name="delete" value='1' class="toggle deleteckb" data-name="{%=file.name%}" data-uri="{%=file.path%}" data-used="{% if (file.delete_ckb) { %}0{% } else { %}1{% } %}">
		{% } %}
		</td>
		{% if (file.error) { %}
		<td class="name"><span>{%=file.name%}</span></td>
		<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
		<td class="error" colspan="2" class="span2"><span class="label label-important">Erreur</span>
			{% if (file.error === 1) { %}Le fichier téléchargé excède la taille de upload_max_filesize, configurée dans le php.ini
			{% } else if ( file.error === 2) { %}Le fichier téléchargé excède la taille de MAX_FILE_SIZE, qui a été spécifiée dans le formulaire HTML
			{% } else if ( file.error === 3) { %}Le fichier n'a été que partiellement téléchargé
			{% } else if ( file.error === 4) { %}Aucun fichier n'a été téléchargé
			{% } else if ( file.error === 5) { %}Missing a temporary folder
			{% } else if ( file.error === 6) { %}Un dossier temporaire est manquant
			{% } else if ( file.error === 7) { %}Échec de l'écriture du fichier sur le disque (disque plein ?)
			{% } else if ( file.error === 8) { %}Une extension PHP a arrêté l'envoi de fichier
			{% } else { %} {%=file.error%} 
			{% } %}
		</td>
		{% } else { %}
		<td class="name">
			{% if (file.url) { %}
			<a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-uri="{%=file.path%}">{%=file.name%}</a>
			{% } else { %}
			{%=file.name%}
			{% } %}
		</td>
		<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
		<td colspan="2"></td>
		{% } %}
		<td class="action" style="text-align:right">
			<button class="btn btn-danger delete" {% if (!file.delete_type) { %} style="display:none" {% } %} data-name="{%=file.name%}" data-uri="{%=file.path%}" data-type="{%=file.delete_type%}" data-url="{%=file.delete_url%}"{% if (file.delete_with_credentials) { %} data-xhr-fields='{"withCredentials":true}'{% } %}>
			<i class="icon-trash"></i>
			<span>Supprimer</span>
			</button>
		</td>
	</tr>
	{% } %}
</script>
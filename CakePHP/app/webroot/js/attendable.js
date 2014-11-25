/**
 * attendable v.1.0
 * Requires: jQuery v1.3+
 * Copyright (c) 2012 Florent Veyrès
 */
(function($) {
$.fn.attendable = function(options) {
	$.fn.attendable.options = $.extend({}, $.fn.attendable.defaults, options);

	if ($.fn.attendable.options.loaderImgSrc)
		$(document.body).append($('<img>').attr('id', 'attendablePreLoaderImg').attr('src', $.fn.attendable.options.loaderImgSrc).hide());

	return this.each(function(index) {
		// gestion du confirm
		var onClickAttr = $(this).attr('onclick');
		if (onClickAttr == undefined )
			onClickAttr = '$(this).attendableAffiche();';
		else
			onClickAttr = onClickAttr.replace('confirm(', '$(this).attendableAffiche(');
		$(this).attr('onclick', onClickAttr);
	});
};

$.fn.attendableAffiche = function(confirmTxt) {
	if ((typeof confirmTxt !== "undefined") && confirmTxt !== '' && !confirm(confirmTxt))
		return false;

    var domModal = $('<div>').attr('id', $.fn.attendable.options.modalDomId);
	//Fix si modale a été supprimer, recharger l'image du loader
	if ($.fn.attendable.options.loaderImgSrc && $("#attendablePreLoaderImg").length == 0)
		$(document.body).append($('<img>').attr('id', 'attendablePreLoaderImg').attr('src', $.fn.attendable.options.loaderImgSrc).hide());
	
	if ($.fn.attendable.options.loaderImgSrc)
		domModal.append($('#attendablePreLoaderImg').css('display', '').detach()).append('<br><br>');

    if ($(this).attr('data-attendable-message'))
        message = $(this).attr('data-attendable-message');
    else
        message = $.fn.attendable.options.message;

    domModal.append(message);
	$(document.body).append(domModal);
	$(document.body).append($('<div>').attr('id', $.fn.attendable.options.overlayDomId));
	$.fn.attendableResize();

	return true;
};

$.fn.attendableResize = function() {
	// initialisation
	var domOverlay = $('#'+$.fn.attendable.options.overlayDomId);
	if (domOverlay.length == 0) return;

	// taille et position de l'overlay
	var domToOverlay = $('#'+$.fn.attendable.options.toOverlayDomId);
	domOverlay
		.css('left', domToOverlay.offset().left)
		.css('top', domToOverlay.offset().top)
		.width(domToOverlay.outerWidth())
		.height(domToOverlay.outerHeight());

	// position du div modal
	var domModal = $('#'+$.fn.attendable.options.modalDomId);
	var modalTop = Math.max(0, (($(window).height() - domModal.outerHeight()) / 3) + $(window).scrollTop()) + "px";
	var modalLeft = Math.max(0, (($(window).width() - domModal.outerWidth()) / 2) + $(window).scrollLeft()) + "px";
	domModal.css('left', modalLeft).css('top', modalTop);
};

$.fn.attendableRemove = function() {
    var domOverlay = $('#'+$.fn.attendable.options.overlayDomId);
    if (domOverlay.length) domOverlay.remove();
    var modalDomId = $('#'+$.fn.attendable.options.modalDomId);
    if (modalDomId.length) modalDomId.remove();
    $(this).removeAttr('onclick');
};

$.fn.attendable.options = {};

$.fn.attendable.defaults = {
	toOverlayDomId: 'container',
	overlayDomId: 'overlayAttendable',
	modalDomId: 'modalAttendable',
	message: 'Veuillez patienter',
	loaderImgSrc: ''
};

})(jQuery);

$(window).resize(function(){
	$.fn.attendableResize();
});

function loadImage(el, src) {
	var objImagePreloader = new Image();
	objImagePreloader.onload = function() {
		el.attr('src', src);
	};
	objImagePreloader.src = src;
 }
 

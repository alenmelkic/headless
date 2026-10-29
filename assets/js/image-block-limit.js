(function () {
	var MAX_IMAGES = 5;
	var data = window.headlessImageLimit || {};
	if (data.exempt) return;

	var select = wp.data.select;
	var dispatch = wp.data.dispatch;
	var subscribe = wp.data.subscribe;
	var LOCK_KEY = 'headless-image-limit';
	var NOTICE_ID = 'headless-image-limit';
	var prevCount = -1;

	subscribe(function () {
		var blocks = select('core/block-editor').getBlocks();
		var count = countImageBlocks(blocks);

		if (count === prevCount) return;
		prevCount = count;

		if (count > MAX_IMAGES) {
			dispatch('core/editor').lockPostSaving(LOCK_KEY);
			dispatch('core/notices').createErrorNotice(
				'Maksimalno ' + MAX_IMAGES + ' slike su dozvoljene po članku. Za više slika koristite galeriju. Uklonite ' + (count - MAX_IMAGES) + ' sliku/e da biste mogli spremiti.',
				{ id: NOTICE_ID, isDismissible: false }
			);
		} else if (count <= MAX_IMAGES) {
			dispatch('core/editor').unlockPostSaving(LOCK_KEY);
			dispatch('core/notices').removeNotice(NOTICE_ID);
		}
	});

	function countImageBlocks(blocks) {
		var count = 0;
		for (var i = 0; i < blocks.length; i++) {
			if (blocks[i].name === 'core/image') count++;
			if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) {
				count += countImageBlocks(blocks[i].innerBlocks);
			}
		}
		return count;
	}
}());

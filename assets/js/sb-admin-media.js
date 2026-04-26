jQuery(function($){
    $(document).on('click', '.sb-manage-gallery-btn', function(e){
        e.preventDefault();
        var button = $(this);
        var input = $('#' + button.data('input'));
        var preview = $('#' + button.data('preview'));
        
        var frame = wp.media({
            title: 'Select Gallery Images',
            button: { text: 'Add to Gallery' },
            multiple: true
        });

        frame.on('select', function(){
            var selection = frame.state().get('selection');
            var urls = input.val() ? input.val().split('\n') : [];
            
            selection.map(function(attachment){
                attachment = attachment.toJSON();
                if (attachment.url && !urls.includes(attachment.url)) {
                    urls.push(attachment.url);
                }
            });
            
            input.val(urls.join('\n'));
            renderGalleryPreview(urls, preview, input);
        });

        frame.open();
    });

    $(document).on('click', '.sb-gallery-remove', function(){
        var btn = $(this);
        var url = btn.data('url');
        var input = $('#' + btn.data('input'));
        var preview = btn.closest('.sb-gallery-preview');
        
        var urls = input.val().split('\n').filter(function(u){
            return u !== url && u.trim() !== '';
        });
        
        input.val(urls.join('\n'));
        renderGalleryPreview(urls, preview, input);
    });

    function renderGalleryPreview(urls, container, input) {
        container.empty();
        urls.forEach(function(url){
            if (!url.trim()) return;
            container.append(
                '<div class="sb-gallery-item">' +
                '<img src="' + url + '" />' +
                '<button type="button" class="sb-gallery-remove" data-url="' + url + '" data-input="' + input.attr('id') + '">✕</button>' +
                '</div>'
            );
        });
    }

    // ─── List / Amenities Logic ───
    $(document).on('click', '.sb-add-list-item', function(e){
        e.preventDefault();
        var wrap = $(this).closest('.sb-list-wrap');
        var input = wrap.find('.sb-list-input');
        var hidden = wrap.find('.sb-list-hidden');
        var preview = wrap.find('.sb-list-preview');
        var val = input.val().trim();
        
        if (!val) return;
        
        var current = hidden.val() ? hidden.val().split(',').map(function(s){ return s.trim(); }) : [];
        if (!current.includes(val)) {
            current.push(val);
        }
        
        hidden.val(current.join(', '));
        input.val('');
        renderListPreview(current, preview, hidden);
    });

    $(document).on('click', '.sb-list-remove', function(){
        var btn = $(this);
        var val = btn.data('val');
        var wrap = btn.closest('.sb-list-wrap');
        var hidden = wrap.find('.sb-list-hidden');
        var preview = wrap.find('.sb-list-preview');
        
        var current = hidden.val().split(',').map(function(s){ return s.trim(); }).filter(function(s){
            return s !== val && s !== '';
        });
        
        hidden.val(current.join(', '));
        renderListPreview(current, preview, hidden);
    });

    function renderListPreview(items, container, hidden) {
        container.empty();
        items.forEach(function(item){
            if (!item.trim()) return;
            container.append(
                '<div class="sb-list-tag">' +
                '<span>' + item + '</span>' +
                '<button type="button" class="sb-list-remove" data-val="' + item + '">✕</button>' +
                '</div>'
            );
        });
    }
});

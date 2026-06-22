jQuery(document).ready(function($) {
    const $title = $('#eseo_meta_title');
    const $desc = $('#eseo_meta_description');
    const $keyword = $('#eseo_focus_keyword');
    const $results = $('#eseo-analysis-results');
    const $content = $('#content'); // Classic editor

    // Inject progress bars into DOM
    $title.after('<div class="eseo-progress-container"><div id="eseo-title-progress" class="eseo-progress-bar"></div></div>');
    $desc.after('<div class="eseo-progress-container"><div id="eseo-desc-progress" class="eseo-progress-bar"></div></div>');

    function updateProgressBar($el, currentLength, maxLength, warningThreshold) {
        let percentage = (currentLength / maxLength) * 100;
        if (percentage > 100) percentage = 100;
        
        $el.css('width', percentage + '%');
        $el.removeClass('warning danger');

        if (currentLength > maxLength) {
            $el.addClass('danger');
        } else if (currentLength > warningThreshold) {
            $el.addClass('warning');
        }
    }
    
    function analyzeSEO() {
        let resultsHTML = '';
        const keywordVal = $keyword.val() ? $keyword.val().trim().toLowerCase() : '';
        const titleVal = $title.val() ? $title.val().trim() : '';
        const descVal = $desc.val() ? $desc.val().trim() : '';
        let contentVal = '';
        
        // Update progress bars
        updateProgressBar($('#eseo-title-progress'), titleVal.length, 60, 50);
        updateProgressBar($('#eseo-desc-progress'), descVal.length, 160, 140);
        
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            // Gutenberg
            const editor = wp.data.select('core/editor');
            contentVal = editor ? editor.getEditedPostContent() : '';
        } else {
            // Classic
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                contentVal = tinymce.get('content').getContent({format: 'text'});
            } else if ($content.length) {
                contentVal = $content.val();
            }
        }

        if (!keywordVal) {
            resultsHTML += '<li style="color:red;">❌ Please enter a focus keyword.</li>';
        } else {
            // Check keyword in title
            if (titleVal.toLowerCase().includes(keywordVal) || (titleVal === '' && $('#title').val() && $('#title').val().toLowerCase().includes(keywordVal))) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in SEO title.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in SEO title.</li>';
            }

            // Check keyword in description
            if (descVal.toLowerCase().includes(keywordVal)) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in meta description.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in meta description.</li>';
            }

            // Check keyword in content
            if (contentVal && contentVal.toLowerCase().includes(keywordVal)) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in content.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in content.</li>';
            }
        }

        // Title length check
        const actualTitleLength = titleVal ? titleVal.length : ($('#title').val() ? $('#title').val().length : 0);
        if (actualTitleLength > 0 && actualTitleLength <= 60) {
            resultsHTML += '<li style="color:green;">✅ SEO title length is good (' + actualTitleLength + ' chars).</li>';
        } else if (actualTitleLength > 60) {
            resultsHTML += '<li style="color:orange;">⚠️ SEO title is too long.</li>';
        }

        // Description length check
        if (descVal.length >= 120 && descVal.length <= 160) {
            resultsHTML += '<li style="color:green;">✅ Meta description length is good (' + descVal.length + ' chars).</li>';
        } else {
            resultsHTML += '<li style="color:orange;">⚠️ Meta description length should be between 120-160 chars.</li>';
        }

        $results.html(resultsHTML);
    }

    // Bind events
    $title.on('input', analyzeSEO);
    $desc.on('input', analyzeSEO);
    $keyword.on('input', analyzeSEO);
    $('#title').on('input', analyzeSEO); // Default WP title

    // For Gutenberg, listen to content changes
    if (typeof wp !== 'undefined' && wp.data) {
        wp.data.subscribe(function () {
            // Throttling could be added here for performance
            analyzeSEO();
        });
    }

    // AI Generation
    $('.eseo-ai-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const type = $btn.data('type');
        const keyword = $keyword.val() ? $keyword.val().trim() : '';

        if (!keyword) {
            alert('Please enter a Focus Keyword first.');
            return;
        }

        const originalText = $btn.text();
        $btn.text('⏳ Generating...').prop('disabled', true);

        let contentVal = '';
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            const editor = wp.data.select('core/editor');
            contentVal = editor ? editor.getEditedPostContent() : '';
        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            contentVal = tinymce.get('content').getContent({format: 'text'});
        } else if ($content.length) {
            contentVal = $content.val();
        }

        $.ajax({
            url: eseo_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'eseo_generate_meta',
                nonce: eseo_vars.ai_nonce,
                type: type,
                keyword: keyword,
                content: contentVal.substring(0, 3000) // limit content sent
            },
            success: function(response) {
                if (response.success) {
                    if (type === 'title') {
                        $title.val(response.data).trigger('input');
                    } else {
                        $desc.val(response.data).trigger('input');
                    }
                } else {
                    if (response.data && response.data.code === 'no_api_key') {
                        if (confirm(response.data.message + '\n\nClick OK to go to settings.')) {
                            window.location.href = 'admin.php?page=eseo-ai-settings';
                        }
                    } else {
                        alert('Error: ' + (response.data.message || response.data));
                    }
                }
            },
            error: function() {
                alert('AJAX request failed.');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });

    // Initial analysis
    setTimeout(analyzeSEO, 1000);
});

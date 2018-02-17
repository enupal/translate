/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

(function($)
{
    /**
     * EnupalTranslate class
     */
    var EnupalTranslate = Garnish.Base.extend({

        options: null,

        /**
         * The constructor.
         */
        init: function()
        {
            console.log(Craft.elementIndex.updateElements());
            //this.addListener($("#showError1"), 'activate', 'showError');
            //this.addListener($("#loginfo"), 'activate', 'showLoginfo');

            // Get locale menu btn
            var $localeMenuBtn = $('.sitemenubtn:first').menubtn().data('menubtn').menu;

            // Get locale form element
            var $localeFormElm = $('input[name="siteId"]');

            // Get translations download button
            $downloadBtn = $('.translations-download-button');

            // Init form with selected locale, if any
            if(Craft.getLocalStorage('BaseElementIndex.siteId')) {
                $localeFormElm.val(Craft.getLocalStorage('BaseElementIndex.siteId'));
                $downloadBtn.attr('href', $downloadBtn.attr('href').replace(/siteId=.*$/, 'siteId=' + Craft.getLocalStorage('BaseElementIndex.siteId')));
            }

            // Change locale on select
            $localeMenuBtn.on('optionselect', function(ev) {
                $localeFormElm.val($(ev.selectedOption).data('siteId'));
                $downloadBtn.attr('href', $downloadBtn.attr('href').replace(/siteId=.*$/, 'siteId=' + $(ev.selectedOption).data('siteId')));
            });

            // Upload file on click
            $('.translations-upload-button').click(function() {
                $('input[name="translations-upload"]').click().change(function() {
                    $(this).parent('form').submit();
                });
            });
        },


    });

    window.EnupalTranslate = EnupalTranslate;

})(jQuery);
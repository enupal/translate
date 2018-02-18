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
            this.addListener($('#save-elements-button'), 'activate', 'processAjaxCall');
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

        onAfterAction: function(action, params)
        {
            console.log("as");
            Craft.elementIndex.updateElements();
        },

        processAjaxCall: function(event)
        {
            event.preventDefault();
            var data = $("#translate-ajax").serialize();
            Craft.postActionRequest('enupal-translate/translate/save', data, $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    if (response.success)
                    {
                        Craft.cp.displayNotice(Craft.t('enupal-translate', 'Translations have been saved successfully.'));
                        Craft.elementIndex.updateElements();
                    }
                }
                else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                }
            }, this));
        }
    });

    window.EnupalTranslate = EnupalTranslate;

})(jQuery);
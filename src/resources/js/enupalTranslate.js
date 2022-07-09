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
        $toHeader:null,
        $menu: null,
        $form: null,

        /**
         * The constructor.
         */
        init: function()
        {
            var that = this;
            this.addListener($('.save-elements-button'), 'activate', 'processAjaxCall');
            // Add support for CMD + S
            this.addListener(Garnish.$doc, 'keydown', function(ev) {
                if (Garnish.isCtrlKeyPressed(ev) && ev.keyCode === Garnish.S_KEY) {
                    that.processAjaxCall(ev);
                }

                return true;
            });
            this.$form = $("#translate-ajax");
            var settings = {};
            this.$menu = new Garnish.MenuBtn("#enupal-menubtn", settings);
            var $menuBtn = $('.sitemenubtn:first').menubtn().data('menubtn');
            var $siteMenu = null;

            // check is only one site on craft cms (we don't have a dropdown menu if only 1 site)
            if (typeof $menuBtn != "undefined") {
                $siteMenu = $menuBtn.menu;
            }

            var $siteIdInput = $('input[name="siteId"]');
            var $importSiteId = $('input[name="importSiteId"]');

            // Upload file on click
            $('.translations-upload-button').click(function() {
                $('input[name="translations-upload"]').click().change(function() {
                    $(this).parent('form').submit();
                });
            });

            $('.translations-sync-button').click(function() {
                $("#sync-db-form").submit();
            });

            // Init the form
            // Figure out the initial site to Translate
            var siteIdToTranslate = Craft.getLocalStorage('BaseElementIndex.siteId');

            if (typeof $menuBtn != "undefined") {
                var $option = $siteMenu.$options.filter('.sel:first');
                if (!$option.length) {
                    $option = $siteMenu.$options.first();
                }

                if ($option.length) {
                    siteIdToTranslate = $option.data('site-id');
                }

                $siteMenu.on('optionselect', function(ev) {
                    $siteIdInput.val($(ev.selectedOption).data('siteId'));
                    $importSiteId.val($(ev.selectedOption).data('siteId'));
                });

                $siteIdInput.val(siteIdToTranslate);
                $importSiteId.val(siteIdToTranslate);
            } else {
                $siteIdInput.val($importSiteId.val());
            }

            Craft.elementIndex.on('afterAction', this.manageAfterAction);

            // Manage Menu
            this.$menu.on('optionSelect', function(event) {
                // Download action
                if (event.option.dataset.process == 'download'){
                    var data = {
                        siteId: Craft.elementIndex.siteId,
                        sourceKey: Craft.elementIndex.sourceKey
                    };

                    Craft.postActionRequest('enupal-translate/translate/download', data, $.proxy(function(response, textStatus) {
                        if (textStatus === 'success') {
                            if (response.success)
                            {
                                if (response.filePath){
                                    var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('enupal-translate/translate/download-csv-file', {'filepath': response.filePath})}).hide();
                                    $("#translate-ajax").append($iframe);
                                    Craft.cp.displayNotice(Craft.t('enupal-translate', 'Downloading file'));
                                }
                                else {
                                    Craft.cp.displayError(Craft.t('app', 'There was an error when generating the file'));
                                }
                            }
                            else {
                                Craft.cp.displayError(Craft.t('app', 'Please select a different source'));
                            }
                        }
                        else {
                            Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                        }
                    }, this));
                } else if (event.option.dataset.process == 'save'){
                    that.processAjaxCall(event);
                }

            });
        },

        manageAfterAction: function(action, params)
        {
            Craft.elementIndex.updateElements();
        },

        processAjaxCall: function(event)
        {
            if (typeof event.preventDefault === "function") {
                event.preventDefault();
            }
            var data = this.$form.serializeArray();
            data.push({name: 'sourceKey', value: Craft.elementIndex.sourceKey});
            Craft.postActionRequest('enupal-translate/translate/save', data, $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    if (response.success)
                    {
                        Craft.cp.runQueue();
                        Craft.cp.displayNotice(Craft.t('enupal-translate', 'Translations saved'));
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
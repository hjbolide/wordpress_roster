(function (NS, $) {
    $.extend(NS, {
        init: function () {
            this.bind();
            this.roster = {};
        },
        bind: function () {
            $('input[data-edik-elem="search"]').on('keyup', function (e) {
                var $target = $(e.target),
                    $people = $target.closest('article').find('[data-edik-elem="people-grid"]'),
                    text = $target.val(),
                    match = [];

                if (!text || text.length < 3) {
                    $people.find('[data-edik-elem-type="people"]').show();
                    $people.find('[data-edik-elem-type="people"][cloned]').remove();
                    return;
                }

                $people.find('[data-edik-elem-type="people"]').each(function (i, p) {
                    var $p = $(p);
                    if ($p.attr('cloned')) {
                        $p.remove();
                        return;
                    } else {
                        $p.show();
                    }
                    if ($p.data('edik-elem').indexOf(text.toUpperCase()) > -1) {
                        match.push($p);
                    }
                });

                if (match) {
                    $.each(match, function (i, $e) {
                        $people.prepend($e.clone({withDataAndEvents: true}).attr('cloned', 'cloned'));
                        $e.hide();
                    });
                }
            });
            this._bind_on_select_person();
        },
        _bind_on_select_person: function () {
            $('input[type="checkbox"][data-edik-elem-type="person"]').on('change', function (e) {
                var $target = $(e.target),
                    elem_id = $target.data('edik-elem'),
                    elem_title = $target.data('edik-elem-title');
                if ($target.parent()[0].hasAttribute('cloned')) {
                    $target
                        .closest('article')
                        .find('[data-edik-elem="' + elem_id + '"][data-edik-elem-title="' + elem_title + '"]')
                        .prop('checked', $target.prop('checked'));
                }
            });
        },
        on_save: function () {
            $.post(NS.ajax_url, {
                roster: JSON.stringify(this.roster),
                action: 'save_roster'
            }, $.proxy(this.on_save_finished, this));
        },
        on_save_finished: function () {
            $('div#message').slideDown(500).delay(3000).fadeOut(500);
        }
    });

    $(document).ready(function () {
        NS.init();
    });

} (NGRosterAdmin, jQuery));

(function (NS, $) {
    $.extend(NS, {
        init: function () {
            this.bind();
        },
        bind: function () {
            $('input#roster_save').on('click', $.proxy(this.on_save, this));
        },
        on_save: function () {
            $.post(NS.ajax_url, {
                roster: JSON.stringify(this.get_roster()),
                action: 'save_roster'
            }, $.proxy(this.on_save_finished, this));
        },
        get_roster: function () {
            var data = {};
            $('table#roster_table').find('tbody > tr').each(function (i, m) {
                var $tr = $(m);
                var pid = parseInt($tr.find('input[name="pid"]').val(), 10);
                data[pid] = {};
                $tr.find('input[name^="weekday_"]').each(function (i, w) {
                    var $w = $(w);
                    data[pid][parseInt($w.val(), 10)] = $w.prop('checked');
                });
            });
            return data;
        },
        on_save_finished: function (response) {
            $('div#message').fadeIn(500).delay(5000).fadeOut(500);
        }
    });

    $(document).ready(function () {
        NS.init();
    });

} (RosterAdmin, jQuery));

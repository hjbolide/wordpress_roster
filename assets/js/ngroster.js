(function (NS, $) {
    $.extend(NS, {
        init: function () {
            this.bind();
            this.roster = {};
        },
        bind: function () {
            this._bind_on_search_person();
            this._bind_on_select_person();
            this._bind_on_save();
            this._on_bind_finished();
        },
        _on_bind_finished: function () {
            $('article').each(function (i, m) {
                var $this = $(m),
                    $people_grid = $this.find('div[data-edik-elem="people-grid"]');
                $people_grid.find('input:checked[type="checkbox"][data-edik-elem-type="person"]')
                    .closest('div.person-grid')
                    .detach()
                    .prependTo($people_grid);
            });
        },
        _bind_on_save: function () {
            $('input#roster_save').on('click', $.proxy(function () {
                console.log(this.roster);
                $.post(NS.ajax_url, {
                    roster: JSON.stringify(this.roster),
                    action: 'save_ngroster'
                }, $.proxy(this.on_save_finished, this));
            }, this));
        },
        _bind_on_search_person: function () {
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
                        var $new_e = $e.clone({withDataAndEvents: true}).attr('cloned', 'cloned').prependTo($people).hide();
                        $e.hide();
                        $new_e.fadeIn(600);
                    });
                }
            });
        },
        _bind_on_select_person: function () {
            $('input[type="checkbox"][data-edik-elem-type="person"]').on('change', $.proxy(function (e) {
                var $target = $(e.target),
                    elem_id = $target.data('edik-elem'),
                    elem_title = $target.data('edik-elem-title'),
                    $people_grid = $target.closest('div[data-edik-elem="people-grid"]'),
                    date = $target.closest('li').attr('id'),
                    checked = $target.prop('checked');

                if (!(elem_title in this.roster)) {
                    this.roster[elem_title] = {};
                }
                this.roster[elem_title][date] = checked;

                if ($target.parent()[0].hasAttribute('cloned')) {
                    $target
                        .closest('article')
                        .find('[data-edik-elem="' + elem_id + '"][data-edik-elem-title="' + elem_title + '"]')
                        .prop('checked', checked);
                }

                var $person_grid = $target.closest('div.person-grid').detach().hide();
                if (checked) {
                    $person_grid.prependTo($people_grid).fadeIn(1000);
                } else {
                    $person_grid.appendTo($people_grid).fadeIn(1000);
                }
            }, this));
        },
        on_save_finished: function (response) {
            this.roster = {};
            $('div#message').slideDown(500).delay(3000).fadeOut(500);
        }
    });

    $(document).ready(function () {
        NS.init();
    });

} (NGRosterAdmin, jQuery));

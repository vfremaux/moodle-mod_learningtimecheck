
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// jshint unused: true, undef:true

define(['jquery', 'core/log', 'core/config'], function($, log, cfg) {

    var ltcreport = {

        init: function() {
            $('#ltc-event-filter-toggle-handle').bind('click', this.rule_filter_toggle);
            $('#ltc-add-rule').bind('click', this.load_filter_rule_form);
            log.debug("AMD Ltc reports initialized");
        },

        load_filter_rule_form: function (e) {

            e.preventDefault();
            e.stopPropagation();

            var that = $(this);
            var cmid = that.attr('data-cmid');
            var component = that.attr('data-component');
            var view = that.attr('data-view');
            var itemid = that.attr('data-itemid');

            var url = cfg.wwwroot;
            url += '/' + component;
            url += '/learningtimecheck/ajax/services.php?';
            url += 'id=' + cmid;
            url += '&what=getfilterruleform';
            url += '&view=' + view;
            url += '&itemid=' + itemid;

            $.get(url, function(data) {
                $('#ltc-filter-new-rule').html(data);
                $('#ltc-filter-new-rule').removeClass('hidden');
                $('#ltc-new-rule-cancel').bind('click', ltcreport.cancel_filter_rule_form);
            });

            return false;
        },

        cancel_filter_rule_form: function () {
            $('#ltc-filter-new-rule').html('');
            $('#ltc-filter-new-rule').addClass('hidden');
        },

        rule_filter_toggle: function (e) {

            e.preventDefault();
            e.stopPropagation();

            var filterformid = '#ltc-event-filter-form';
            if ('none' == $(filterformid).css('display')) {
                $(filterformid).css('display', 'block');
                $('#ltc-event-filter-toggle-handle i').removeClass('fa-plus');
                $('#ltc-event-filter-toggle-handle i').addClass('fa-minus');
                $('#ltc-event-filter-toggle-handle').attr('aria-expanded', true);
            } else {
                $(filterformid).css('display', 'none');
                $('#ltc-event-filter-toggle-handle i').removeClass('fa-minus');
                $('#ltc-event-filter-toggle-handle i').addClass('fa-plus');
                $('#ltc-event-filter-toggle-handle').attr('aria-expanded', false);
            }

            return false;
        }
    };

    return ltcreport;
});
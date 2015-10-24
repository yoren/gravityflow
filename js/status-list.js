
(function (GravityFlowStatusList, $) {
    var page = 1, filters;
    $(document).ready(function () {
        $("#doaction").click(function(){

            var action = $('#bulk-action-selector-top').val();

            if ( action == 'print' ) {
                var checkedValues = $('.gravityflow-cb-entry-id:checked').map(function() {
                    return this.value;
                }).get();
                printPage( gravityflow_status_list_strings.ajaxurl + '?action=gravityflow_print_entries&lid=' + checkedValues.join(',') );
                return false;
            }

		});

        $('.gravityflow-export-status-button').click(function(){
            var $this = $(this);
            $this.addClass('button-disabled');
            filters = $this.data('filter_args');
            var s = $this.next('.spinner');
            $this.next('.gravityflow-spinner').show();
            processExport();
        });

        function processExport(){
            var url;
            url = ajaxurl + '?action=gravityflow_export_status&order=asc&paged=' + page;
            url += filters;
            $.getJSON(url, function(data){
                if ( data.status =='complete' ) {
                    window.location = data.url;
                } else if( data.status =='incomplete' ) {
                    processExport( page++ );
                } else {
                    alert(data.message);
                }
                $('.gravityflow-export-status-button.button-disabled').next('.gravityflow-spinner').hide();
                $('.gravityflow-export-status-button.button-disabled').removeClass('button-disabled');
            });
        }
    });



}(window.GravityFlowStatusList = window.GravityFlowStatusList || {}, jQuery));

function closePrint () {
    document.body.removeChild(this.__container__);
}

function setPrint () {
    this.contentWindow.__container__ = this;
    this.contentWindow.onbeforeunload = closePrint;
    this.contentWindow.onafterprint = closePrint;
    this.contentWindow.focus();
    var ms_ie = false;
    var ua = window.navigator.userAgent;
    var old_ie = ua.indexOf('MSIE ');
    var new_ie = ua.indexOf('Trident/');

    if ((old_ie > -1) || (new_ie > -1)) {
        ms_ie = true;
    }

    if ( ms_ie ) {
        this.contentWindow.document.execCommand('print', false, null);
    } else {
        this.contentWindow.print();
    }
}

function printPage (sURL) {
    var oHiddFrame = document.createElement("iframe");
    oHiddFrame.onload = setPrint;
    oHiddFrame.style.visibility = "hidden";
    oHiddFrame.style.position = "fixed";
    oHiddFrame.style.right = "0";
    oHiddFrame.style.bottom = "0";
    oHiddFrame.src = sURL;
    document.body.appendChild(oHiddFrame);
}

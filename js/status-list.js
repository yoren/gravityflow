
(function (GravityFlowStatusList, $) {
    $(document).ready(function () {
        $("#doaction").click(function(){
            var checkedValues = $('.gravityflow-cb-step-id:checked').map(function() {
                return this.value;
            }).get();
            printPage( gravityflow_status_list_strings.ajaxurl + '?action=gravityflow_print_entries&lid=' + checkedValues.join(',') );
        	return false;
		});
    });

}(window.GravityFlowStatusList = window.GravityFlowStatusList || {}, jQuery));

function closePrint () {
    document.body.removeChild(this.__container__);
}

function setPrint () {
    this.contentWindow.__container__ = this;
    this.contentWindow.onbeforeunload = closePrint;
    this.contentWindow.onafterprint = closePrint;
    this.contentWindow.focus(); // Required for IE
    this.contentWindow.print();
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


(function (GravityFlowEntryDetail, $) {
    GravityFlowEntryDetail.printPage = function (sURL) {
        printPage (sURL);
    };

}(window.GravityFlowEntryDetail = window.GravityFlowEntryDetail || {}, jQuery));

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

function displayDiscussionItemToggle(formId, fieldId, displayCount) {
    
    if( jQuery('#field_' + formId + '_' + fieldId) ) {
        displayCount = displayCount;
        
        jQuery('#field_' + formId + '_' + fieldId + ' .gravityflow-discussion-item:gt(' + displayCount + ')').toggle();
        
        oldText = jQuery('#field_' + formId + '_' + fieldId + ' .gravityflow-dicussion-item-toggle-display').attr('title');
        newText = jQuery('#field_' + formId + '_' + fieldId + ' .gravityflow-dicussion-item-toggle-display').data('title');
        
        jQuery('#field_' + formId + '_' + fieldId + ' .gravityflow-dicussion-item-toggle-display').attr('title', newText).text(newText);
        jQuery('#field_' + formId + '_' + fieldId + ' .gravityflow-dicussion-item-toggle-display').data('title', oldText);
        
    }
}


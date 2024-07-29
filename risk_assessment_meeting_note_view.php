<style>
    @media print {
        body {
            height: 1400px;
        }
        .menu-sticky-top, #tblAssessmentInfo, .row, .tableFixHead{
            display: none;
        }
        #dialogMeetingNote {
            visibility: hidden !important;
            width:100% !important;
            height: 100% !important;
            left: 0 !important;
            top: 0 !important;
            /* border: 1px solid #999; */
            min-height: 1400px !important;
        }
        #dialogNoteContents {
            visibility: visible;
            overflow: visible;
            position: absolute;
            left: 5% !important;
            top: 0 !important;
            margin: 0;
            padding: 0;
        }
        .gridlines {
            height: 100% !important;
            transform-origin: 0 0;
            left: 0px !important;
            top: 0 !important;
            /* border: 1px solid #999; */
            transform: scale(1.5, 1.36);
        }
        .notPrint {
            visibility: hidden !important;
        }
        #meetingNoteDetail {
            display: flex !important;
            justify-content: center !important;
        }

        #meetingNoteGrid {
            justify-content: flex-start !important;
        }

        @page {
            size: A4;
            margin: 1.3cm;
        }
    }
</style>
<script type="text/javascript" src="vendor/ckeditor/ckeditor/ckeditor.js"></script>
<script>
$(document).ready(function() {
    //회의록작성 창
    $('#dialogEditMeetingNote').jqxWindow({
        width: 1000,
        height: 800, 
        // resizable: false,
        autoOpen: false,
        // isModal: true,
        cancelButton: $('#btnCancelEditMeetingNote')
    });
    $("#dialogEditMeetingNote").css("visibility", "visible");

    //회의록상세 창
    $('#dialogMeetingNote').jqxWindow({
        width: 1000,
        height: 800, 
        // resizable: false,
        autoOpen: false,
        // isModal: true,
        cancelButton: $('#btnCancelMeetingNote')
    });
    $("#dialogMeetingNote").css("visibility", "visible");

    var maxWidth = 300;
    CKEDITOR.replace('textareaContent', {
        width: '99.8%',
        height: "600px",
//         resize_maxHeight: "395px",
        autoParagraph: false,
        allowedContent: true,
        enterMode : CKEDITOR.ENTER_BR, 
        removeButtons: '',
        removePlugins: 'elementspath',
        resize_enabled: false,
        extraPlugins : 'font,colorbutton,justify,tableresize,specialchar',
        specialChars : CKEDITOR.config.specialChars.concat( [ [ '&#8361;', '원화 기호' ] ] ),
        toolbar: [
            { name: 'document', items: [ 'Source' ] },
            { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo' ] },
            { name: 'links', items: [ 'Link', 'Unlink' ] },
            { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule', 'SpecialChar' ] },
            { name: 'tools', items: [ 'Maximize' ] },
            '/',
            { name: 'styles', items: [ 'Font', 'FontSize' ] },
            { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat' ] },
            { name: 'colors', items: [ 'TextColor', 'BGColor' ] },
            { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock' ] }
        ],
        filebrowserImageUploadUrl: 'common/editor_file_upload.php?moduleName=meeting&jno=' + $("#jno").val() + '&type=Images',
//         forcePasteAsPlainText : true,
        contentsCss: 'body{margin: 20px;width: 640px;}table{border-collapse: collapse;}',
        on: {
            instanceReady: function(evt) {
                CKEDITOR.instances.textareaContent.widgets.registered.uploadimage.onUploaded = function(e) {
                    var img = this.parts.img.$;
                    var width = e.responseData.width||img.naturalWidth;
                    var height = e.responseData.height||img.naturalHeight;
                    if (width > maxWidth) {
                        height = Math.round(maxWidth * (height / width));
                        width = maxWidth; 
                    }
                    this.replaceWith( '<img src="' + e.url + '" ' + 'width="' + width + '" ' + 'height="' + height + '">' );
                }
            }
        }
    });
    CKEDITOR.on('dialogDefinition', function(evt) {
        // Take the dialog name and its definition from the event data.
        var dialogName = evt.data.name;
        var dialog = evt.data.definition.dialog;

        dialog.on('show', function () {
            //이미지 정보 탭
            if (dialogName == 'image') {
                //너비
                var ele = this.getContentElement('info', 'txtWidth');
                //유효성 검사
                ele.validate = function(e) {
                    var y=/(^\s*(\d+)((px)|\%)?\s*$)|^$/i;
                    var a=this.getValue().match(y);
                    a=!(!a||0===parseInt(a[1],10));
                    if (a) {
                        if(ele.getValue() > maxWidth) {
                            alert("이미지 너비는 " + maxWidth + "px 이하로 지정해주세요.");
                            a = !a;
                        }
                    }
                    else {
                        alert(CKEDITOR.instances.textareaContent.lang.common.invalidLength.replace("%1",CKEDITOR.instances.textareaContent.lang.common.width).replace("%2","px, %"));
                    }
                    return a;
                }
            }
        });
    });
//     CKEDITOR.instances.textareaContent.on('change', function() {
//         if ($("#editingArticle").val() == "Y") {
// //         $("#detectEditArticle").val("Y");
//             validateContent();
//         }
//     });
    CKEDITOR.instances.textareaContent.on('paste', function (evt) {
        evt.data.dataValue = evt.data.dataValue.replace(/<span[^>]*?>/g, '');
        evt.data.dataValue = evt.data.dataValue.replace(/<font[^>]*?>/g, '');
    });


    //회의록 버튼 클릭
    $('#btnOpenMeetingNote').on("click", function () {
        showMeetingNote();
    });

    //회의록 편집 클릭
    $('#btnEditMeetingNote').on("click", function () {
        $("#dialogMeetingNote").jqxWindow("close");
        editMeetingNote();
    });

    //회의내용 저장
    $("#btnSaveMeetingNote").on("click", onBtnSaveMeetingNoteClick);
});

//회의내용 저장 버튼 클릭
function onBtnSaveMeetingNoteClick() {
    $("#mode").val("SAVE_MEETING_NOTE");

    var html = CKEDITOR.instances.textareaContent.getSnapshot();
    var dom = document.createElement("DIV");
    dom.innerHTML = html;
    var txt = (dom.textContent || dom.innerText);
    $("#contentsTxt").val(txt);
    $("#contents").val(CKEDITOR.instances.textareaContent.getData());

    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_meeting_detail.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            if(result["proceed"]) {
                $("#dialogEditMeetingNote").jqxWindow("close");
                showMeetingNote();
            }
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//회의록 편집
function editMeetingNote() {
    var mno = $("#mno").val();

    if(!mno) {
        $.ajax({ 
            type: "POST", 
            url: "risk/get_html_content.php", 
            data: $("#mainForm").serialize(),
            dataType: "html", 
            success: function(result) {
                var content = result;
                CKEDITOR.instances.textareaContent.setData(content);
            }
        });
    }
    $('#dialogEditMeetingNote').jqxWindow('open');
}

//회의록 상세
function showMeetingNote() {
    $("#mode").val("MEETING_NOTE");

    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_meeting_detail.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            var meetingNoteContent = result["meetingNoteContent"];
            if(!$.isEmptyObject(meetingNoteContent) && meetingNoteContent["contents"]) {
                $("#mno").val(meetingNoteContent["mno"]);
                $("#meetingNoteDetail").html(meetingNoteContent["contents"]);
                CKEDITOR.instances.textareaContent.setData(meetingNoteContent["contents"]);
                $("#dialogMeetingNote").jqxWindow("open");
            } else {
                $("#mno").val('');
                editMeetingNote();
            }
        }
    });
}
</script>
<div id="dialogMeetingNote" style="visibility: hidden;height: 0;">
    <div id="dialogNoteHeader">
        회의록
    </div>
    <div id="dialogNoteContents">
            <div class="d-flex justify-content-center" id="meetingNoteGrid">
                <div id="meetingNoteDetail" class="d-flex justify-content-center" style="width:600px; text-align:center"></div>
            </div>
            <div class="d-flex justify-content-around mt-3 notPrint">
                <button type="button" id="btnPrintMeetingNote" class="btn btn-primary" onclick="window.print()" style="display:none">인쇄</button>
                <button type="button" id="btnEditMeetingNote" class="btn btn-primary" style="display:none">편집</button>
                <button type="button" id="btnCancelMeetingNote" class="btn btn-secondary">닫기</button>
            </div>
        </div>
    </div>
</div>
<div id="dialogEditMeetingNote" style="visibility: hidden;height: 0;">
    <div id="dialogNoteHeader">
        회의록 작성
    </div>
    <div id="dialogNoteContents">
        <textarea id="textareaContent"></textarea>
        <div class="d-flex justify-content-around mt-3">
            <button type="button" id="btnSaveMeetingNote" class="btn btn-primary">저장</button>
            <button type="button" id="btnCancelEditMeetingNote" class="btn btn-secondary">닫기</button>
        </div>
    </div>
</div>
<input type="hidden" id="contentsTxt" name="contentsTxt" />
<input type="hidden" id="contents" name="contents" />
<input type="hidden" id="mno" name="mno" />

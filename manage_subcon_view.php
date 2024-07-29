<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="pragma" content="no-cache" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="google" content="notranslate">
<title>안전보건관리</title>
<link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../fontawesome-free-6.0.0-web/css/all.css">
<link rel="stylesheet" href="../jquery/jquery-ui-1.13.0/jquery-ui.min.css" />
<link rel="stylesheet" href="../jqwidgets-ver14.0.0-src/styles/jqx.base.css" type="text/css" />
<link rel="stylesheet" href="../jqwidgets-ver14.0.0-src/styles/jqx.ui-smoothness.css" type="text/css" />
<link rel="stylesheet" href="../css/style.css" />
<script type="text/javascript" src="../jquery/jquery-3.6.0.min.js"></script>
<script type="text/javascript" src="../vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="../jquery/jquery-ui-1.13.0/jquery-ui.min.js"></script>
<script type="text/javascript" src="../jquery/jquery-ui-1.13.0/i18n/datepicker-ko.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxcore.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxdata.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxbuttons.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxscrollbar.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxmenu.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxcheckbox.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxlistbox.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxdropdownlist.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.sort.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.pager.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.selection.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.edit.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.filter.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.storage.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.columnsresize.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxgrid.columnsreorder.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxexpander.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxvalidator.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxcalendar.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxdatetimeinput.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxmaskedinput.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxinput.js"></script> 
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxwindow.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxpanel.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxtree.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/jqxdragdrop.js"></script>

<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/globalization/globalize.js"></script>
<script type="text/javascript" src="../jqwidgets-ver14.0.0-src/globalization/globalize.culture.ko-KR.js"></script>
<style>
    body {
        margin: 5%;
    }
</style>
</head>

<body>
<script>
$(document).ready(function() {
    $("#mode").val("INIT");

    $("#jno").val(<?php echo $_GET["jno"]?>);
    $("#cno").val(<?php echo $_GET["cno"]?>);

    $.ajax({
        type: "POST", 
        url: "manage_subcon.php", 
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var subconDetail = result["subconDetail"];
            //사업자 번호
            var regNo = subconDetail["regNo"].replace(/-/g,'');
            $("#regNo").val(regNo);
            //회사 이름
            $("#compNm").val(subconDetail["compName"]);

            var html = '';

            html += '<tr>';
            html += '<td>사업자번호</td>';
            html += '<td>' + subconDetail["regNo"] + '</td>';
            html += '</tr>';
            html += '<tr>';
            html += '<td>업체명</td>';
            html += '<td>' + subconDetail["compName"] + '</td>';
            html += '</tr>';
            html += '<tr>';
            html += '<td>사용자명</td>';
            html += '<td>' + subconDetail["ceo"] + '</td>';
            html += '</tr>';

            $("#tblSubconDetail").append(html);

            //공종(현장) 리스트
            html = '';
            $(result["disciplineList"]).each(function(i, info) {
                var disKey = '';
                if(result["disciplineDetail"]) {
                    disKey = Object.keys(result["disciplineDetail"]);
                }
                html += '<div class="form-check-inline">';
                html += '<label class="form-check-label">';
                if(disKey.includes(info["funcNo"]) == true && disKey != '') {
                html += '<input type="checkbox" class="form-check-input validateElement" value="'+ info["funcNo"] +'" name="chkDisciplineList[]" checked required>' + info["funcName"];
                } else {
                html += '<input type="checkbox" class="form-check-input validateElement" value="'+ info["funcNo"] +'" name="chkDisciplineList[]" required>' + info["funcName"];
                }
                html += '</label>';
                html += '</div>';
            });
            html += '<div class="invalid-feedback"></div>';

            $("#chkDiscipline").append(html);

            //협력업체 정보
            var id = result["subconInfo"]["id"];
            var pw = result["subconInfo"]["pw"];
            var supervisor = '';
            if(result["subconInfo"]["userName"]) {
                supervisor = result["subconInfo"]["userName"] + ' ' + result["subconInfo"]["dutyName"];
            }
            var unoSV = result["subconInfo"]["uno"];
            var cellphone = result["subconInfo"]["cellphone"];
            var email = result["subconInfo"]["email"];

            $("#subconId").val(id);
            $("#subconPwd").val(pw);
            $("#supervisorNm").val(supervisor);
            $("#unoSV").val(unoSV);
            $("#subconPhone").val(cellphone);
            $("#subconEmail").val(email);

        },
        complete: function() {
            $("input[name='chkDisciplineList[]']").on('click', validateDiscipline);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });

    //관리감독자 선택 모달 활성화
    basicDemo.init();
    //관리감독자 리스트
    showSVList();
    //관리감독자 clear
    $("#btnDelSV").on('click' , function() {
        $("#supervisorNm").val('');
        $("#unoSV").val('');
    });
    //저장 버튼
    $("#btnSave").on('click', onBtnSaveClick);
    //모달 닫기 버튼
    $("#closeSVSel").jqxButton();
    $("#closeSVSel").on('click', function() {
        $('#SVSelWindow').jqxWindow('close');
    });
    //닫기 버튼
    $("#btnCloseSubcon").on('click', function() {
        window.close();
    });
});
//관리감독자 선택 모달
var basicDemo = (function () {
    //Adding event listeners
    function _addEventListeners() {
        $('#btnSelSV').click(function () {
            $('#SVSelWindow').jqxWindow('open');
        });
    };
    //Creating the demo window
    function _createWindow() {
        $('#SVSelWindow').jqxWindow({ autoOpen: false }); 
        var jqxWidget = $('#jqxWidget');
        var offset = jqxWidget.offset();
        $('#SVSelWindow').jqxWindow({
            position: { x: offset.left + 100, y: offset.top + 50} ,
            showCollapseButton: true, 
            maxWidth: 800, minHeight: 600, minWidth: 800, height: 600, width: 800,
            resizable: false,
            initContent: function () {
                $('#SVSelWindow').jqxWindow('focus');
            }
        });
    };
    return {
        config: {
            dragArea: null
        },
        init: function () {
            //Attaching event listeners
            _addEventListeners();
            //Adding jqxWindow
            _createWindow();
        }
    };
} ());

//관리감독자 선택리스트
function showSVList() {
    var url = "/manage/manage_user_list_data.php";
    // prepare the data
    var source =
    {
        datatype: "json",
        datafields: [
            { name: 'uno', type: 'int' },
            { name: 'userName', type: 'string' },
            { name: 'dutyName', type: 'string' },
            { name: 'deptPath', type: 'string' }
        ],
        id: 'jno',
        url: url,
        pager: function (pagenum, pagesize, oldpagenum) {
            // callback called when a page or page size is changed.
        }
    };
    var dataAdapter = new $.jqx.dataAdapter(source);
    $("#SVListGrid").jqxGrid(
    {
        width: "100%",
        source: dataAdapter,
        sortable: true,
        pageable: true,
        autorowheight: true,
        autoheight: true,
        altrows: true,
        autoloadstate: false,
        autosavestate: false,
        columnsresize: true,
        columnsreorder: true,
        showfilterrow: true,
        filterable: true,
        pagermode: 'simple',
        altrows: true,
        columns: [
            { text: '부서', datafield: 'deptPath'},
            { text: '성명', datafield: 'userName', width: 150 },
            { text: '직급', datafield: 'dutyName', width: 150 },
            { text: '선택', datafield: '선택', columntype: 'button', width: 80
                , cellsrenderer: function () {
                    return "선택";
                }
                , buttonclick: function (row) {
                    var rowData = $('#SVListGrid').jqxGrid('getrowdata', row);
                    onSupervisorSelClick(rowData)
                }
            }
        ]
    });
}

//관리감독자 선택버튼
function onSupervisorSelClick(row) {
    var userNm = row["userName"];
    var dutyNm = row["dutyName"];
    var uno = row["uno"];

    $("#supervisorNm").val(userNm + ' ' + dutyNm);
    $("#unoSV").val(uno);

    $('#SVSelWindow').jqxWindow('close');
}

//저장 버튼
function onBtnSaveClick() {
    $("#mode").val("SAVE");
    
    if(validateDiscipline()) {
        $.ajax({
            type: "POST", 
            url: "manage_subcon.php", 
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                alert("저장되었습니다.");
                opener.location.reload();
                window.close();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//공종 필수 입력
function validateDiscipline() {
    var isExtDiscipline = $("input[type='checkbox'][name='chkDisciplineList[]']:checked").length;

    if(isExtDiscipline > 0) {
        $(".validateElement").closest(".form-group").find(".invalid-feedback").html("");
        $(".validateElement").closest(".form-group").find(".invalid-feedback").hide();

        return true
    } else {
        $(".validateElement").closest(".form-group").find(".invalid-feedback").html("하나이상의 공종을 선택하세요");
        $(".validateElement").closest(".form-group").find(".invalid-feedback").show();

        // return false
    }
}
</script>
<form id="mainForm" name="mainForm">
<div class="ibox-title">
    <i class="fa-solid fa-user"></i> 협력업체 정보
</div>
<div class="d-flex mb-2" style="border-bottom: 2px solid #666666;"></div>
<br />
<table class="table table-bordered table-sm" id="tblSubconDetail">
    <tbody>
    </tbody>
</table>
<div class="ibox-content ifr-ibox-content-full-height">
    <div class="form-group">
        <label class="col-xs-4 control-label" for="subconId">아이디</label>
        <div class="col-xs-8">
            <input type="text" class="form-control" id="subconId" name="subconId" readonly />
        </div>
    </div>
    <div class="form-group">
        <label class="col-xs-4 control-label requiredvalue" for="subconPwd">비밀번호</label>
        <div class="col-xs-8">
            <input type="text" class="form-control required" name="subconPwd" id="subconPwd" readonly />
        </div>
    </div>
    <!-- <div class="form-group">
        <label class="col-xs-4 control-label" for="supervisorNm">관리감독자</label>
        <div class="col-xs-8">
            <div class="input-group">
                <input type="text" class="form-control" id="supervisorNm" name="supervisorNm" readonly>
                <input type="hidden" id="unoSV" name="unoSV">
                <span class="btn btn-danger" id="btnDelSV"><i class="fa-solid fa-x"></i></span>
                <span class="btn btn-primary" id="btnSelSV"><i class="fa-solid fa-magnifying-glass"></i></span>
            </div>
        </div>
    </div> -->
    <label class="col-xs-4 control-label" for="discipline">공종(현장)</label>
    <div class="form-group container border p-2" id="chkDiscipline"></div>
    <div class="form-group">
        <label class="col-xs-4 control-label requiredvalue" for="subconPhone">휴대전화</label>
        <div class="col-xs-8">
            <input type="text" class="form-control" id="subconPhone" name="subconPhone">
        </div>
    </div>
    <div class="form-group">
        <label class="col-xs-4 control-label" for="subconEmail">이메일</label>
        <div class="col-xs-8">
            <input type="text" class="form-control mask-email" id="subconEmail" name="subconEmail">
        </div>
    </div>
    <br />
    <div class="d-flex justify-content-around">
        <a class="btn btn-primary" id="btnSave"><span><i class="fa-solid fa-floppy-disk"></i> 저장</span></a>
        <button type="button" id="btnCloseSubcon" class="btn btn-secondary">닫기</button>
    </div>
</div>

<!--  선택 모달 -->
<div id="jqxWidget" style="display:none">
    <div id="mainDemoContainer">
        <div id="SVSelWindow">
            <div id="SVSelWindowHeader">
                <span>관리감독자 선택</span>
            </div>
            <div id="SVSelWindowContent"> 
                <div id="SVListGrid"></div>
                <button id="closeSVSel" style="margin-top:5px;float:right">닫기</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="cno" name="cno" />
<input type="hidden" id="jno" name="jno" />
<input type="hidden" id="regNo" name="regNo" />
<input type="hidden" id="compNm" name="compNm" />
</form>
</body>

</html>

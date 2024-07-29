<style>
    #tblMajorCd td {
        cursor:pointer;
    }
    #tblMajorCd tbody .detail:hover {
        background-color:#FFE5CC;
    }
    #tblMajorCd .active {
        background-color:#FFE5CC;
    }
    .table th, .table td {
        vertical-align: middle !important;
    }
    .uppercase {
        text-transform: uppercase;
    }
</style>
<script>
$(document).ready(function() {
    //분류코드 설명 가져오기
    importMajorCd();
    //분류코드 저장
    $("#btnSaveMajorCd").on('click', onBtnSaveMajorCdClick);
    //요소코드 저장
    $("#btnSaveMinorCd").on('click', onBtnSaveMinorCdClick);
    //분류코드 추가 버튼
    $("#btnAddMajorCd").on('click', onBtnAddMajorCdClick);
    //요소코드 추가 버튼
    $("#btnAddMinorCd").on('click', onBtnAddMinorCdClick);
});

function onChangeMajorCodeList(majorCd, obj) {
    $("#mode").val("LIST");
    //분류코드 변경
    $("#majorCd").val(majorCd);
    
    $("#tblMajorCd tr").removeClass("active");
    
    $("#chkAll").prop('checked', false);
    
    $.ajax({
        type: "POST",
        url: "manage/manage_code.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var html = '';
            
            $(result["minorCodeList"]).each(function(i, info) {
                html += '<tr>';
                // html += '<td class="col-1"><input type="checkbox" name="delMinorCd[]" value="'+ info["minorCd"] +'" onclick="chkClick()"></td>';
                html += '<td class="col-4">';
                if(info["isUse"] == "N") {
                    html += '<del>';
                    html += info["minorCd"];
                    html += '</del>';
                } else {
                    html += info["minorCd"];
                }
                html += '</td>';
                html += '<td class="col-4">';
                if(info["isUse"] == "N") {
                    html += '<del>';
                    html += info["cdNm"];
                    html += '</del>';
                } else {
                    html += info["cdNm"];
                }
                html += '</td>';
                html += '<td class="col-1 text-center">' + info["val5"] + '</td>';
                html += '<td class="col-1 text-center">'+ info["isUse"] +'</td>';
                html += '<td class="col-2 text-center">';
                html += '<span class="btn btn-info btn-sm mr-2" onclick="onBtnModifyCd(this, \'Minor\', \'' + info["minorCd"] + '\', \'' + info["isUse"] + '\')">수정</span>';
                html += '<span class="btn btn-info btn-sm" onclick="onBtnDelCdClick(\'minor\', \'' + info["minorCd"] + '\')">삭제</span>';
                html += '</td>';
                html += '</tr>';
                html += '<tr style="display:none">';
                // html += '<td class="col-1"><input type="checkbox" name="delMinorCd[]" value="'+ info["minorCd"] +'" onclick="chkClick()" disabled></td>';
                html += '<td class="col-4 form-group">'
                html += '<input type="text" class="form-control validateElement minorCd uppercase" name="modifyMinorCd['+ info["minorCd"] +']" value="' + info["minorCd"] + '" disabled required/>';
                html += '<div class="invalid-feedback"></div>';
                html += '</td>';
                html += '<td class="col-4 form-group">'
                html += '<input type="text" class="form-control validateElement minorCd" name="modifyMinorCdNm['+ info["minorCd"] +']" value="' + info["cdNm"] + '" disabled required/>';
                html += '<div class="invalid-feedback"></div>';
                html += '</td>';
                html += '<td class="col-1 text-center">'
                html += '<input type="number" class="form-control" name="modifyMinorCdSort['+ info["minorCd"] +']" value="' + info["val5"] + '" disabled/>';
                html += '</td>';
                html += '<td class="col-1 text-center">'
                if(info["isUse"] == "Y") {
                    html += '<span class="btn btn-primary btn-sm" onclick="onBtnMinorCdIsUseClick(this, \'' + info["minorCd"] + '\', \'N\')">사용</span>';

                    html += '<span class="btn btn-danger btn-sm" onclick="onBtnMinorCdIsUseClick(this, \'' + info["minorCd"] + '\', \'Y\')" style="display:none">미사용</span>';
                } else {
                    html += '<span class="btn btn-danger btn-sm" onclick="onBtnMinorCdIsUseClick(this, \'' + info["minorCd"] + '\', \'Y\')">미사용</span>';
                    html += '<span class="btn btn-primary btn-sm" onclick="onBtnMinorCdIsUseClick(this, \'' + info["minorCd"] + '\', \'N\')" style="display:none">사용</span>';
                }
                html += '</td>';
                html += '<td class="col-2 text-center"><span class="btn btn-info btn-sm" onclick="onBtnCancelMinorCd(this, \'' + info["minorCd"] + '\', \'' + info["cdNm"] + '\', \'' + info["val5"] + '\', \'' + info["isUse"] + '\')">취소</span></td>';
                html += '</tr>';
            });
            
            $("#tblMinorCd tbody").empty().append(html);
        },
        complete: function() {
            if(majorCd) {
                $('input[value="' + majorCd + '"]').closest("tr").addClass("active");
                $('input[value="' + majorCd + '"]').closest("tr").prev("tr").addClass("active");
            } else {
                $(obj).addClass("active");
            }
            
            //유효성 검사
            addValidate();

            //요소추가 버튼
            if($("#tblMajorCd").find(".active").length == 0) {
                $("#btnAddMinorCd").hide();
                $("#btnSaveMinorCd").hide();
            } else {
                $("#btnAddMinorCd").show();
                $("#btnSaveMinorCd").show();
            }

            //코드 대문자변환
            $(".uppercase").bind("keyup", function() {
                $(this).val($(this).val().toUpperCase());
            });
        },
        error: function(request, status, error) {
            alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
        }
    });
}

//요소코드 수정버튼
function onBtnModifyCd(obj, type, majorCd, isUse) {
    $(obj).closest("tr").find("input").prop("disabled", true);
    $(obj).closest("tr").hide();

    $(obj).closest("tr").next("tr").find("input").prop("disabled", false);
    $(obj).closest("tr").next("tr").show();

    var hidden = '';
    if(type == "Major") {
        hidden += '<input type="hidden" name="majorCdIsUse['+ majorCd +']" value="'+ isUse +'"/>';
        $("#hiddenMajorCdIsUse").append(hidden);
    } else {
        hidden += '<input type="hidden" name="minorCdIsUse['+ majorCd +']" value="'+ isUse +'"/>';
        $("#hiddenMinorCdIsUse").append(hidden);
    }

}

//분류코드 설명 취소버튼
function onBtnCancelMajorCd(obj, majorCd, cdNm, isUse) {
    if(majorCd && cdNm) {
        $(obj).closest("tr").hide();
        $(obj).closest("tr").prev("tr").find("input").prop("disabled", false);
        $(obj).closest("tr").prev("tr").show();
    
        $(obj).closest("tr").find("input").eq(0).val(majorCd);
        $(obj).closest("tr").find("input").eq(1).val(cdNm);
        $(obj).closest("tr").find("input").prop("disabled", true);
        if(isUse == 'Y') {
            $(obj).closest("tr").find(".btn-danger").hide();
            $(obj).closest("tr").find(".btn-primary").show();
        } else {
            $(obj).closest("tr").find(".btn-danger").show();
            $(obj).closest("tr").find(".btn-primary").hide();
        }
        var hidden = '';
        hidden += '<input type="hidden" name="majorCdIsUse['+ majorCd +']" value="'+ isUse +'"/>';
        $("#hiddenMajorCdIsUse").append(hidden);
    } else {
        $(obj).closest("tr").remove();
    }
}

//요소코드 취소버튼
function onBtnCancelMinorCd(obj, minorCd, cdNm, val5, isUse) {
    if(minorCd && cdNm) {
        $(obj).closest("tr").hide();
        $(obj).closest("tr").prev("tr").find("input").prop("disabled", false);
        $(obj).closest("tr").prev("tr").show();
        
        $(obj).closest("tr").find("input[type=text]").eq(0).val(minorCd);
        $(obj).closest("tr").find("input[type=text]").eq(1).val(cdNm);
        $(obj).closest("tr").find("input[type=number]").eq(2).val(val5);
        $(obj).closest("tr").find("input").prop("disabled", true);
        if(isUse == 'Y') {
            $(obj).closest("tr").find(".btn-danger").hide();
            $(obj).closest("tr").find(".btn-primary").show();
        } else {
            $(obj).closest("tr").find(".btn-danger").show();
            $(obj).closest("tr").find(".btn-primary").hide();
        }
        var hidden = '';
        hidden += '<input type="hidden" name="minorCdIsUse['+ minorCd +']" value="'+ isUse +'"/>';
        $("#hiddenMinorCdIsUse").append(hidden);
    } else {
        $(obj).closest("tr").remove();
    }
}

//분류코드 저장 버튼
function onBtnSaveMajorCdClick() {
    $("#mode").val("SAVE_MAJOR");

    //유효성 검사
    var validCnt = 0;
    $(".majorCd").not("input:disabled").each(function() {
        valid = validateElement(this);
        if(valid == false) {
            validCnt++;
        }
    });
       
    if(validCnt == 0) {
        if(!$("#majorCd").val()) {
            var majorCd = $("#tblMajorCd .active").find("input").eq(0).val();
            $("#majorCd").val(majorCd);
        }
        
        $.ajax({
            type: "POST",
            url: "manage/manage_code.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                if(result["msg"]) {
                    $("#alertModal .modal-body").text(result["msg"]);
                    $("#alertModal").modal("show");
                } else {
                    //분류코드 설명 가져오기
                    importMajorCd();
                    //hidden 비우기
                    $("#hiddenMajorCdIsUse").empty();
                    //요소코드 가져오기
                    onChangeMajorCodeList($("#majorCd").val());
                }
            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        }); 
    }
}

//요소코드 저장 버튼
function onBtnSaveMinorCdClick() {
    $("#mode").val("SAVE_MINOR");

    //유효성 검사
    var validCnt = 0;
    $(".minorCd").not("input:disabled").each(function() {
        valid = validateElement(this);
        if(valid == false) {
            validCnt++;
        }
    });
       
    if(validCnt == 0) {
        if(!$("#majorCd").val()) {
            var majorCd = $("#tblMajorCd .active").find("input").eq(0).val();
            $("#majorCd").val(majorCd);
        }
        
        $.ajax({
            type: "POST",
            url: "manage/manage_code.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                if(result["msg"]) {
                    $("#alertModal .modal-body").text(result["msg"]);
                    $("#alertModal").modal("show");
                } else {
                    //요소코드 가져오기
                    onChangeMajorCodeList($("#majorCd").val());
                    //hidden 비우기
                    $("#hiddenMinorCdIsUse").empty();
                }
            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        }); 
    }
}

//분류코드 설명 가져오기
function importMajorCd() {
    $("#mode").val("INIT");

    $.ajax({
        type: "POST",
        url: "manage/manage_code.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var html = '';
            $(result["majorCodeList"]).each(function(i,info) {
                html += '<tr class="detail" onclick="onChangeMajorCodeList(\'' + info["majorCd"] + '\', this)">';
                html += '<td class="col-4">';
                if(info["isUse"] == "N") {
                    html += '<del>';
                    html += info["majorCd"];
                    html += '</del>';
                } else {
                    html += info["majorCd"];
                }
                html += '</td>';
                html += '<td class="col-4">';
                if(info["isUse"] == "N") {
                    html += '<del>';
                    html += info["cdNm"];
                    html += '</del>';
                } else {
                    html += info["cdNm"];
                }
                html += '</td>';
                html += '<td class="col-2 text-center">';
                html += info["isUse"];
                html += '</td>';
                html += '<td class="col-2 text-center">';
                html += '<span class="btn btn-info btn-sm mr-2" onclick="onBtnModifyCd(this, \'Major\', \'' + info["majorCd"] + '\', \'' + info["isUse"] + '\')">수정</span>';
                html += '<span class="btn btn-info btn-sm" onclick="onBtnDelCdClick( \'major\', \''+ info["majorCd"] +'\')">삭제</span>';
                html += '</td>';
                html += '</tr>';
                html += '<tr style="display:none">';
                html += '<td class="form-group col-4">';
                html += '<input type="text" class="form-control validateElement majorCd uppercase" name="modifyMajorCd['+ info["majorCd"] +']" value="' + info["majorCd"] + '" disabled required/>';
                html += '<div class="invalid-feedback"></div>';
                html += '</td>';
                html += '<td class="form-group col-4">';
                html += '<input type="text" class="form-control validateElement majorCd" name="modifyMajorCdNm['+ info["majorCd"] +']" value="' + info["cdNm"] + '" disabled required/>';
                html += '<div class="invalid-feedback"></div>';
                html += '</td>';
                html += '<td class="col-2 text-center">';
                if(info["isUse"] == "Y") {
                    html += '<span class="btn btn-primary btn-sm" onclick="onBtnMajorCdIsUseClick(this, \'' + info["majorCd"] + '\', \'N\')">사용</span>';
                    html += '<span class="btn btn-danger btn-sm" onclick="onBtnMajorCdIsUseClick(this, \'' + info["majorCd"] + '\', \'Y\')" style="display:none">미사용</span>';
                } else {
                    html += '<span class="btn btn-primary btn-sm" onclick="onBtnMajorCdIsUseClick(this, \'' + info["majorCd"] + '\', \'N\')" style="display:none">사용</span>';
                    html += '<span class="btn btn-danger btn-sm" onclick="onBtnMajorCdIsUseClick(this, \'' + info["majorCd"] + '\', \'Y\')">미사용</span>';
                }
                html += '</td>';
                html += '<td class="col-2 text-center">';
                html += '<span class="btn btn-info btn-sm" onclick="onBtnCancelMajorCd(this, \'' + info["majorCd"] + '\', \'' + info["cdNm"] + '\', \'' + info["isUse"] + '\')">취소</span>';
                html += '</td>';
                html += '</tr>';
            });

            $("#tblMajorCd tbody").empty().append(html);
        },
        error: function(request, status, error) {
            alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
        }
    });
}

//분류코드 추가 버튼
function onBtnAddMajorCdClick() {
    var html = '';
    html += '<tr onclick="onChangeMajorCodeList(null, this)">';
    html += '<td class="form-group">';
    html += '<input type="text" class="form-control validateElement majorCd uppercase" name="addMajorCd[]" required/>';
    html += '<div class="invalid-feedback"></div>';
    html += '</td>';
    html += '<td class="form-group">';
    html += '<input type="text" class="form-control validateElement majorCd" name="addMajorCdNm[]" required/>';
    html += '<div class="invalid-feedback"></div>';
    html += '</td>';
    html += '<td class="text-center">';
    html += 'Y';
    html += '</td>';
    html += '<td class="text-center">';
    html += '<span class="btn btn-info btn-sm" onclick="onBtnCancelMajorCd(this)">취소</span>';
    html += '</td>';
    html += '</tr>';

    $("#tblMajorCd tbody").append(html);
    addValidate();
}

//요소코드 추가 버튼
function onBtnAddMinorCdClick() {
    var html = '';
    html += '<tr>';
    html += '<td class="form-group">';
    html += '<input type="text" class="form-control validateElement minorCd uppercase" name="addMinorCd[]" required/>';
    html += '<div class="invalid-feedback"></div>';
    html += '</td>';
    html += '<td class="form-group">';
    html += '<input type="text" class="form-control validateElement minorCd" name="addMinorCdNm[]" required/>';
    html += '<div class="invalid-feedback"></div>';
    html += '</td>';
    html += '<td>';
    html += '<input type="number" class="form-control" name="addMinorCdSort[]">';
    html += '</td>';
    html += '<td class="text-center">';
    html += 'Y';
    html += '</td>';
    html += '<td class="text-center">';
    html += '<span class="btn btn-info btn-sm" onclick="onBtnCancelMinorCd(this)">취소</span>';
    html += '</td>';
    html += '</tr>';

    $("#tblMinorCd tbody").append(html);
    addValidate();

    //코드 대문자변환
    $(".uppercase").bind("keyup", function() {
        $(this).val($(this).val().toUpperCase());
    });
}

//코드 삭제 버튼
function onBtnDelCdClick(type, cd) {

    $("#confirmModal .modal-body").text("삭제하시겠습니까?");
    $("#confirmModal").modal("show");

    $("#btnDelChk").on('click', function() {
        $("#mode").val("DEL_CODE");
        $("#delMode").val(type);
        $("#delCd").val(cd);
    
        $("#confirmModal").modal("hide");
        $.ajax({
            type: "POST",
            url: "manage/manage_code.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                $("#delMajorCd").val('');
                //분류코드 설명 가져오기
                importMajorCd();
                //요소코드 가져오기
                onChangeMajorCodeList($("#majorCd").val());
                if(type == "major") {
                    $("#btnAddMinorCd").hide();
                }

            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        });
    });

}

// //전체 체크 버튼
// function onBtnChkAllClick(obj) {
//     if($(obj).is(":checked")) {
//         $("input:checkbox[name='delMinorCd[]']").prop("checked" , true);
//     } else {
//         $("input:checkbox[name='delMinorCd[]']").prop("checked" , false);
//     }
// }

// //체크박스 all체크
// function chkClick() {
//     //체크된 박스
//     var checkedBox = $("input:checkbox[name='delMinorCd[]']:checked").not("input:checkbox[name='delMinorCd[]']:disabled").length;
//     //전체 체크 박스
//     var checkboxAll = ($("input:checkbox[name='delMinorCd[]']").length) / 2;

//     if(checkedBox == checkboxAll) {
//         $("#chkAll").prop("checked", true);
//     } else {
//         $("#chkAll").prop("checked", false);
//     }
// }

//유효성 검사
function validateElement(obj) {
    var val = obj.value;
    if(val.trim() == '' || val.trim() == null) {
        $(obj).removeClass("is-valid").addClass("is-invalid");
        $(obj).closest(".form-group").find(".invalid-feedback").html("필수 입력입니다.");
        $(obj).closest(".form-group").find(".invalid-feedback").show();

        return false;
    } else {
        $(obj).removeClass("is-invalid");
        $(obj).closest(".form-group").find(".invalid-feedback").html("");
        $(obj).closest(".form-group").find(".invalid-feedback").hide();

        return true;
    }
}

//값 변동에 따른 유효성 검사
function addValidate() {
    $(".validateElement").each(function() {
            $(this).on("propertychange change keyup input paste", function(event) {
            // If value has changed...
            if ($(this).data('oldVal') != $(this).val()) {
                // Updated stored value
                $(this).data('oldVal', $(this).val());
                validateElement(this);
            }
        });
    });
}

//분류코드 설명 사용유무 변경
function onBtnMajorCdIsUseClick(obj, majorCd, isUse) {
    $(obj).hide();
    $(obj).siblings("span").show();

    var hidden = '';

    hidden += '<input type="hidden" name="majorCdIsUse['+ majorCd +']" value="'+ isUse +'"/>';

    $("#hiddenMajorCdIsUse").append(hidden);
}

//요소코드 사용유무 변경
function onBtnMinorCdIsUseClick(obj, minorCd, isUse) {
    $(obj).hide();
    $(obj).siblings("span").show();

    var hidden = '';

    hidden += '<input type="hidden" name="minorCdIsUse['+ minorCd +']" value="'+ isUse +'"/>';

    $("#hiddenMinorCdIsUse").append(hidden);
}
</script>

<div class="menu-sticky-top">
<ol class="breadcrumb">
    <li class="breadcrumb-item">설정</li>
    <li class="breadcrumb-item">코드 관리</li>
</ol>
<!-- <div class="btnList">
    <button type="button" class="btn btn-primary" id="btnSave"><i class="fa-solid fa-floppy-disk"></i>&nbsp;저장</button>
</div> -->
</div>

<form id="mainForm" name="mainForm" method="post">
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <span class="btn btn-secondary btn-sm mb-2 ml-2" style="float:right;" id="btnSaveMajorCd"><i class="fa-solid fa-floppy-disk"></i>&nbsp;분류코드 저장</span>
            <span class="btn btn-secondary btn-sm mb-2" style="float:right;" id="btnAddMajorCd"><i class="fa-solid fa-plus"></i>&nbsp;분류코드 추가</span>
            <table class="table table-bordered table-sm" id="tblMajorCd">
                <thead>
                    <tr>
                        <th class="col-4">분류코드</th>
                        <th class="col-4">분류코드 설명</th>
                        <th class="col-2">사용유무</th>
                        <th class="col-2">편집</th> 
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div class="col-sm-6">
            <div id="minorButton" style="min-height:33px">
                <span class="btn btn-secondary btn-sm ml-2" id="btnSaveMinorCd" style="float:right;margin-bottom:7.5px;display:none"><i class="fa-solid fa-floppy-disk"></i>&nbsp;요소코드 저장</span>
                <span class="btn btn-secondary btn-sm" id="btnAddMinorCd" style="float:right;margin-bottom:7.5px;display:none"><i class="fa-solid fa-plus"></i>&nbsp;요소코드 추가</span>
            </div>
            <table class="table table-bordered table-sm" id="tblMinorCd">
                <thead>
                    <tr>
                        <!-- <th class="col-1"><input type="checkbox" id="chkAll" onclick="onBtnChkAllClick(this)">&nbsp;삭제</th> -->
                        <th class="col-4">요소코드</th>
                        <th class="col-4">요소코드 설명</th>
                        <th class="col-1">정렬순서</th>
                        <th class="col-1">사용유무</th>
                        <th class="col-2">편집</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 알림모달 -->
<div class="modal fade" id="alertModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            
            <!-- Modal body -->
            <div class="modal-body"></div>
            
            <!-- Modal footer -->
            <div class="modal-footer">
                <div class="container-fluid">
                    <div class="d-flex justify-content-around">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">확인</button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- 삭제확인모달 -->
<div class="modal fade" id="confirmModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            
            <!-- Modal body -->
            <div class="modal-body"></div>
            
            <!-- Modal footer -->
            <div class="modal-footer">
                <div class="container-fluid">
                        <div class="d-flex justify-content-around">
                            <button type="button" class="btn btn-primary" id="btnDelChk">확인</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<div id="hiddenMinorCdIsUse"></div>
<div id="hiddenMajorCdIsUse"></div>
<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="majorCd" name="majorCd" />
<input type="hidden" id="delCd" name="delCd" />
<input type="hidden" id="delMode" name="delMode" />
</form>

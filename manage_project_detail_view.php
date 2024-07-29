<style>
.jqx-dropdownbutton-popup {
    z-index:2000 !important;
}

#tblJobDetail td {
    padding-left : 0.5rem;
}

.jqx-grid-column-header div div {
    text-align:center !important
}

.jqx-checkbox-default div {
    width: 14px !important;
    height: 14px !important;
}

</style>
<script>
$(document).ready(function() {
    $("#mode").val("INIT");
    var pageId = "<?php echo $_POST["page_id"] ?>";
    $("#pageId").text(pageId);
    if (pageId == "manage_project_detail") {
        $("li.breadcrumb-item:first").text("설정");
        $("#jno").val("<?php echo $_POST["jno"] ?>");
        $("#btnListProject").show();
    }
    else if (pageId == "project_detail") { 
        $("li.breadcrumb-item:first").text("프로젝트");
        if(sessionStorage.getItem("jno")) {
            $("#jno").val(sessionStorage.getItem("jno"));
        }
    }

    if($("#jno").val()) {
        $("#divNone").hide();
        $("#divProjectDetail").show();

        $.ajax({
            type: "POST",
            url: "manage/manage_project_detail.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                //JOB 정보
                var jobDetail = result["jobDetail"];
                var html = '';
                html += '<tr>';
                html += '<th>JOB명</th>';
                html += '<td colspan="3">' + jobDetail["jobName"] + '</td>';
                html += '</tr>';
                html += '<tr>';
                html += '<th>JNO</th>';
                html += '<td>' + jobDetail["jno"] + '</td>';
                html += '<th>JOB No.</th>';
                html += '<td>' + jobDetail["jobNo"] + '</td>';
                html += '</tr>';
                html += '<tr>';
                html += '<th>End-User</th>';
                html += '<td>' + jobDetail["compName"] + '</td>';
                html += '<th>Client</th>';
                html += '<td>' + jobDetail["orderCompName"] + '</td>';
                html += '</tr>';
                html += '<tr>';
                html += '<th>시작일</th>';
                html += '<td>' + jobDetail["jobSd"] + '</td>';
                html += '<th>종료일</th>';
                html += '<td>' + jobDetail["jobEd"] + '</td>';
                html += '</tr>';
                html += '<tr>';
                html += '<th>PM</th>';
                html += '<td>' + jobDetail["userName"] + '</td>';
                html += '<th>구분</th>';
                html += '<td>' + jobDetail["jobState"] + ' </td>';
                html += '</tr>';

                $("#tblJobDetail tbody").empty().append(html);

                //현장소장
                var superIntendent = result["superIntendent"];
                if(superIntendent) {
                    var superHtml = '';
                    superHtml += '<tr>';
                    superHtml += '<td>' + superIntendent["userName"] + ' ' + superIntendent["dutyName"] + '</td>';
                    superHtml += '</tr>';
    
                    $("#tblSuperIntendent tbody").append(superHtml);
                }
                //관리자
                $("#editAuth").text(result["editAuth"]);

                //공종(현장) 목록
                html = '';
                var disciplineList = result["disciplineList"];
                $(disciplineList).each(function(i, info) {
                    html += '<div class="form-check-inline">';
                    html += '<label class="form-check-label">';
                    html += '<input type="checkbox" class="form-check-input validateElement" value="'+ info["funcNo"] +'" name="chkDisciplineList[]" form="mainForm" required>' + info["funcName"];
                    html += '</label>';
                    html += '</div>';
                });
                html += '<div class="invalid-feedback"></div>';

                $("#chkDiscipline").append(html);

                //관리 감독자
                importSuperVisor();
                //안전 관리자
                importSMList();
            },
            complete: function() {
                //협력업체 목록
                importSubCon();
                //첨부파일 목록
                importAttachment();
                //관리감독자 공종(현장) 필수입력
                $("input[name='chkDisciplineList[]']").on('click', validateDiscipline);

                //현장소장, 안전관리자
                if ($("#editAuth").text() == "A") {
                    $(".editingArea").show();
                    $(".editingPartArea").show();
                } 
                //관리감독자
                else if($("#editAuth").text() == "P") {
                    $(".editingPartArea").show();
                    $(".editingArea").hide();
                    $("#tblSVList thead tr:eq(1) th:last-child").hide();
                }
                //협력업체
                else {
                    $(".editingArea").hide();
                    $(".editingPartArea").hide();
                    $("#tblSVList thead tr:eq(1) th:last-child").hide();
                    $("#tblSubconList thead th:last-child").hide();
                }
                
                //안전관리자 선택 모달
                SMSelWindow.init();

                //안전관리자 선택 GRID
                showSMList();
                //관리감독자 선택 GRID
                showSVList();
            },
            error: function(request, status, error) {
                alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
            }
        });
    }
    else {
        $("#divNone").show();
        $("#divProjectDetail").hide();
    }

    //안전관리자 반영 버튼
    $("#btnReflectSMSel").on('click', onBtnReflectSMSelClick);

    //관리감독자 반영 버튼
    $("#btnReflectSVSel").on('click', onBtnReflectSVSelClick);

    //안전관리자 선택시
    $('#SMListGrid').on('rowselect', function(event) {
        var smListGrid = $("#SMListGrid");
        var selSMList = smListGrid.jqxGrid('selectedrowindexes');

        var smName = '';
        $.each(selSMList, function(i, row) {
            var rowData = smListGrid.jqxGrid('getrowdata', row);
            smName += rowData['userName'] + ',';
        });

        $("#selSMList").text(smName);
    });

    //안전관리자 선택해제
    $('#SMListGrid').on('rowunselect', function(event) {
        if(event.args.row != undefined) {
            var uno = event.args.row["uno"];
            var userName = event.args.row["userName"];
    
            var selSMList = $("#selSMList").text();
    
            selSMList = selSMList.replace(userName + ",", "");
    
            $("#selSMList").empty().append(selSMList);
        }
    });

    //관리감독자 선택시
    // $('#SVListGrid').on('rowselect', function(event) {
    //     var svListGrid = $("#SVListGrid");
    //     var selSVList = svListGrid.jqxGrid('selectedrowindexes');

    //     var svName = '';
    //     $.each(selSVList, function(i, row) {
    //         var rowData = svListGrid.jqxGrid('getrowdata', row);
    //         svName += rowData['userName'] + ',';
    //     });

    //     $("#selSVList").text(svName);
    //     validateSelSV();
    // });

    //관리감독자 선택해제
    // $('#SVListGrid').on('rowunselect', function(event) {
    //     if(event.args.row != undefined) {
    //         var uno = event.args.row["uno"];
    //         var userName = event.args.row["userName"];
    
    //         var selSVList = $("#selSVList").text();
    
    //         selSVList = selSVList.replace(userName + ",", "");
    
    //         $("#selSVList").empty().append(selSVList);
    //     }
    //     validateSelSV();
    // });

    //안전관리자 모달 open
    $('#SMSelWindow').on('open', smSelWindowOpen); 
    //안전관리자 모달 close
    $('#SMSelWindow').on('close', function(event) {
        $('#SMListGrid').jqxGrid('clearselection');
        $('#selSMList').empty();
    });

    //관리감독자 모달 open
    $('#SVSelWindow').on('open', function(event) {
        $("#selDiscipline option").eq(0).prop('selected', true);
        // onSelDisciplineChange();
    }); 
    //관리감독자 모달 close
    $('#SVSelWindow').on('close', function(event) {
        $("input[type='checkbox'][name='chkDisciplineList[]']:checked").prop('checked', false);
        $("#selSVList").text('');
        $(".selSV").find(".invalid-feedback").html('');
        $(".selSV").find(".invalid-feedback").hide();
        $("#chkDiscipline").find(".invalid-feedback").html('');
        $("#chkDiscipline").find(".invalid-feedback").hide();
        importSuperVisor();
        $("#selUnoSV").val('');
        $('#SVListGrid').jqxGrid('clearselection');
        $("#selectRow").text('');
        $('#SVListGrid').jqxGrid('clearfilters');
        addfilterSV();
        $('#jqxdropdownbutton').jqxDropDownButton('close');
    });

    //목록 버튼
    $("#btnListProject").on('click', function() {
        $("<input>").attr({
            type: "hidden",
            id: "page_id",
            name: "page_id",
            value: "manage_project"
        }).appendTo($("#mainForm"));
        $("#mainForm").attr({
            action: "index.php",
            method: "post",
            target: "_self"
        }).submit();
    });

    //첨부파일 저장 버튼
    $("#btnSaveAttach").on('click', onBtnSaveAttachClick);
    //첨부파일 편집 버튼
    $("#btnModifyAttach").on('click', onBtnModifyAttachClick);
});

//안전관리자 선택 모달
var SMSelWindow = (function() {
    //Adding event listeners
    function _addEventListeners() {
        $('#btnSelSM').click(function() {
            $('#SMSelWindow').jqxWindow('open');
        });
    };
    //Creating the demo window
    function _createWindow() {
        $('#SMSelWindow').jqxWindow({
            autoOpen: false
        });
        var jqxWidget = $('#jqxWidget');
        var offset = jqxWidget.offset();
        $('#SMSelWindow').jqxWindow({
            position: {
                x: offset.left + 500,
                y: offset.top + 75
            },
            showCollapseButton: true,
            maxWidth: 820,
            minHeight: 600,
            minWidth: 820,
            height: 600,
            width: 820,
            isModal: true,
            resizable: false,
            cancelButton: $('#closeSMSel'),
            initContent: function() {
                $('#SMSelWindow').jqxWindow('focus');
            }
        });
    };
    return {
        config: {
            dragArea: null
        },
        init: function() {
            //Attaching event listeners
            _addEventListeners();
            //Adding jqxWindow
            _createWindow();
        }
    };
}());

//관리감독자 선택 모달
var SVSelWindow = (function() {
    //Adding event listeners
    function _addEventListeners() {
        $('#btnSelSV').click(function() {
            $('#SVSelWindow').jqxWindow('open');
        });
    };
    //Creating the demo window
    function _createWindow() {
        var jqxWidget = $('#jqxWidget');
        var offset = jqxWidget.offset();
        $('#SVSelWindow').jqxWindow({
            autoOpen: false,
            draggable: false,
            position: {
                x: offset.left + 500,
                y: offset.top + 75
            },
            showCollapseButton: true,
            maxWidth: 820,
            minHeight: 300,
            minWidth: 820,
            height: 300,
            width: 820,
            isModal: true,
            resizable: false,
            cancelButton: $('#closeSVSel'),
            initContent: function() {
                $('#SVSelWindow').jqxWindow('focus');
            }
        });
    };
    return {
        config: {
            dragArea: null
        },
        init: function() {
            //Attaching event listeners
            _addEventListeners();
            //Adding jqxWindow
            _createWindow();
        }
    };
}());

//안전관리자 선택리스트
function showSMList() {
    var url = "manage/manage_user_list_data.php";
    // prepare the data
    var source = {
        datatype: "json",
        datafields: [{
                name: 'uno',
                type: 'int'
            },
            {
                name: 'userName',
                type: 'string'
            },
            {
                name: 'dutyName',
                type: 'string'
            },
            {
                name: 'deptPath',
                type: 'string'
            }
        ],
        id: 'jno',
        url: url
    };

    var dataAdapter = new $.jqx.dataAdapter(source);
    $("#SMListGrid").jqxGrid({
        width: 800,
        source: dataAdapter,
        // sortable: true,
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
        selectionmode: 'checkbox',
        altrows: true,
        // ready: function() {
        //     addfilter();
        // },
        columns: [
            {
                text: '부서',
                datafield: 'deptPath'
            },
            {
                text: '성명',
                datafield: 'userName',
                width: 150,
                cellsalign: 'center'
            },
            {
                text: '직급',
                datafield: 'dutyName',
                width: 150,
                cellsalign: 'center'
            }
        ]
    });
}

//안전관리자 - 부서
function addfilterSM() {
    var filtergroup = new $.jqx.filter();
    var filter_or_operator = 1;
    var filtervalue = '안전보건팀';
    var filtercondition = 'contains';
    var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
    filtergroup.addfilter(filter_or_operator, filter);
    // add the filters.
    $("#SMListGrid").jqxGrid('addfilter', 'deptPath', filtergroup);
    // apply the filters.
    $("#SMListGrid").jqxGrid('applyfilters');
}

//관리감독자 선택리스트
function showSVList() {
    var url = "manage/manage_user_list_data.php";
    // prepare the data
    var source = {
        datatype: "json",
        datafields: [{
                name: 'uno',
                type: 'int'
            },
            {
                name: 'userName',
                type: 'string'
            },
            {
                name: 'dutyName',
                type: 'string'
            },
            {
                name: 'deptPath',
                type: 'string'
            }
        ],
        id: 'jno',
        url: url,
        pager: function(pagenum, pagesize, oldpagenum) {
            // callback called when a page or page size is changed.
        }
    };
    var dataAdapter = new $.jqx.dataAdapter(source);
    $("#SVListGrid").jqxGrid({
        width: 800,
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
        // selectionmode: 'checkbox',
        altrows: true,
        ready: function() {
            addfilterSV();
        },
        columns: [{
                text: '부서',
                datafield: 'deptPath'
            },
            {
                text: '성명',
                datafield: 'userName',
                width: 150,
                cellsalign: 'center'
            },
            {
                text: '직급',
                datafield: 'dutyName',
                width: 150,
                cellsalign: 'center'
            }
        ]
    });
    $("#jqxdropdownbutton").jqxDropDownButton({
        width: "100%", height: 30
    });
    $("#SVListGrid").on('rowselect', function (event) {
        var args = event.args;
        var row = $("#SVListGrid").jqxGrid('getrowdata', args.rowindex);
        $("#selUnoSV").val(row["uno"]);
        var dropDownContent = '<div id="selectRow" style="position: relative; margin-left: 3px; margin-top: 6px;">'+ row["userName"] + " " + row["dutyName"] +'</div>';
        $("#jqxdropdownbutton").jqxDropDownButton('setContent', dropDownContent);
        $('#jqxdropdownbutton').jqxDropDownButton('close');
        $(".selSV").find(".invalid-feedback").html('');
        $(".selSV").find(".invalid-feedback").hide();
    });
    // $("#SVListGrid").jqxGrid('selectrow', 0);
}
//관리감독자 - 부서
function addfilterSV() {
    var filtergroup = new $.jqx.filter();
    var filter_or_operator = 1;
    var filtervalue = '공사관리';
    var filtercondition = 'contains';
    var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
    filtergroup.addfilter(filter_or_operator, filter);
    // add the filters.
    $("#SVListGrid").jqxGrid('addfilter', 'deptPath', filtergroup);
    // apply the filters.
    $("#SVListGrid").jqxGrid('applyfilters');
}

//안전관리자 가져오기
function importSMList() {
    $("#mode").val("SM_IMPORT");

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var safeManagerList = result["safeManagerList"];
            var userHtml = '';
            $(safeManagerList).each(function(i, user) {
                userHtml += '<tr>';
                userHtml += '<td>'
                userHtml += user["userName"] + ' ' + user["dutyName"];
                //팀장
                if(user["teamLeaderYn"] == "Y") {
                    $("#teamLeaderUno").val(user["uno"]);

                    userHtml += '<span class="badge badge-primary ml-2" style="font-size:13px"><i class="fa-solid fa-crown"></i> 팀장</span>';
                } else {
                    if ($("#editAuth").text() == "A") {
                        userHtml += '<button type="button" class="btn btn-warning btn-sm" style="float:right" onclick="onBtnSelTeamLeaerClick('+ user["uno"] +')"><i class="fa-solid fa-crown"></i> 팀장선택</button>';
                    }
                }
                userHtml += '</td>';
                userHtml += '</tr>';
            });

            $("#tblSMList tbody").empty().append(userHtml);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });

}

//안전관리자 반영 버튼
function onBtnReflectSMSelClick() {

    $("#mode").val("SM_EDIT");

    var smListGrid = $("#SMListGrid");
    var selSMList = smListGrid.jqxGrid('selectedrowindexes');

    var html = '';
    var hidden = '';
    $.each(selSMList, function(i, row) {
        var rowData = smListGrid.jqxGrid('getrowdata', row);

        var userNm = rowData["userName"];
        var uno = rowData["uno"];
        var dutyName = rowData["dutyName"];

        hidden += '<input type="hidden" name="selSafeManagerList[]" value="' + uno + '" />';
    });

    $("#hiddenSMList").append(hidden);

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            $("#hiddenSMList").empty();

            if(result["proceed"]) {
                $('#SMSelWindow').jqxWindow('close');
                importSMList();
            } 
            //현장소장 또는 관리감독자와 중복될 경우
            else {
                alert(result["msg"]);
            }
        },
        error: function(request, status, error) {
            alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
        }
    });
}

//안전관리자 체크
function smSelWindowOpen() {
    $("#mode").val("SM_IMPORT");

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            $('#SMListGrid').jqxGrid('clearfilters');
            var rows = $("#SMListGrid").jqxGrid('getrows');
            
            //체크박스 선택
            $.each(rows, function(index, value) {
                $.each(result["safeManagerList"], function(i, userInfo) {
                    if (userInfo["uno"] == value["uno"]) {
                        $('#SMListGrid').jqxGrid('selectrow', index);
                    }
                });
            });
        },
        complete: function() {
            addfilterSM();
        },
        error: function(request, status, error) {
            alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
        }
    });
}

//협력업체 리스트 가져오기
function importSubCon() {
    $("#mode").val("SUBCON_IMPORT");

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var html = '';

            var disciplineAll = result["disciplineListAll"];
            var disciplineCnt = disciplineAll.length;

            //협력업체 thead
            html += '<tr>';
            html += '<th rowspan="2">업체명</th>';
            html += '<th rowspan="2" class="editingPartArea" rowspan="2">아이디</th>';
            // html += '<th rowspan="2" class="editingPartArea">비밀번호</th>';
            html += '<th rowspan="2" class="editingPartArea">사용자명</th>';
            html += '<th rowspan="2" class="editingPartArea">휴대전화</th>';
            html += '<th rowspan="2" class="editingPartArea">이메일</th>';
            html += '<th colspan="'+ disciplineCnt +'">공종(현장)</th>';
            html += '<th rowspan="2" class="editingPartArea">편집</th>';
            html += '</tr>';
            html += '<tr>';
            $(disciplineAll).each(function(i, info) {
                html += '<th>';
                html += info["funcName"];
                html += '</th>';
            });
            html += '</tr>';

            $("#tblSubconList thead").append(html);

            html = '';
            for(var i in result["subconList"]) {
                if(result["subconInfoList"]) {
                    var infoKey = Object.keys(result["subconInfoList"]);
    
                    if(infoKey.includes(i) == true) {
                        //공종(현장)
                        var disFuncNm = ''
                        if(result["disciplineList"]) {
                            var disCnt = 0;
                            $(result["disciplineList"]).each(function(d, disInfo) {
                                if(disInfo["cno"] == i) {
                                    if(disCnt != 0) {
                                        disFuncNm += "|"
                                    }
                                    disFuncNm += disInfo["funcNo"];
                                    disCnt ++;
                                }
                            });
                        }
                        var funcNoList = disFuncNm.split("|");
                        
                        html += '<tr>';
                        html += '<td>' + result["subconList"][i]["compName"] + '</td>';
                        html += '<td class="editingPartArea">' + result["subconInfoList"][i]["id"] + '</td>';
                        // html += '<td class="editingPartArea">' + result["subconInfoList"][i]["pw"] + '</td>';
                        html += '<td class="editingPartArea">' + result["subconList"][i]["compCeoName"] + '</td>';
                        html += '<td class="editingPartArea">' + result["subconInfoList"][i]["cellphone"] + '</td>';
                        html += '<td class="editingPartArea">' + result["subconInfoList"][i]["email"] + '</td>';
                        $.each(disciplineAll, function(j, funcNo) {
                            html += '<td class="text-center">';
                            $.each(funcNoList, function(k, selFunc) {
                                if(funcNo["funcNo"] == selFunc) {
                                    html += '<i class="fa-solid fa-check"></i>';
                                }
                            });
                            html += '</td>';
                        });
                        if ($("#editAuth").text() == "A" || $("#editAuth").text() == "P") {
                            html += '<td><button type="button" class="btn btn-info btn-sm" onclick="modifySubcon(' + result["subconList"][i]["cno"] + ')"><i class="fa-solid fa-pen-to-square"></i> 편집</button></td>';
                        }
                        html += '</tr>';
                    } else {
                        html += '<tr>';
                        html += '<td>' + result["subconList"][i]["compName"] + '</td>';
                        html += '<td class="editingPartArea"></td>';
                        // html += '<td class="editingPartArea"></td>';
                        html += '<td class="editingPartArea">' + result["subconList"][i]["compCeoName"] + '</td>';
                        html += '<td class="editingPartArea"></td>';
                        html += '<td class="editingPartArea"></td>';
                        $.each(disciplineAll, function(j, funcNo) {
                            html += '<td></td>';
                        });
                        if ($("#editAuth").text() == "A" || $("#editAuth").text() == "P") {
                            html += '<td><button type="button" class="btn btn-info btn-sm" onclick="modifySubcon(' + result["subconList"][i]["cno"] + ')"><i class="fa-solid fa-pen-to-square"></i> 편집</button></td>';
                        }
                        html += '</tr>';
                    }
                } else {
                    html += '<tr>';
                    html += '<td>' + result["subconList"][i]["compName"] + '</td>';
                    html += '<td class="editingPartArea"></td>';
                    // html += '<td class="editingPartArea"></td>';
                    html += '<td class="editingPartArea">' + result["subconList"][i]["compCeoName"] + '</td>';
                    html += '<td class="editingPartArea"></td>';
                    html += '<td class="editingPartArea"></td>';
                    $.each(disciplineAll, function(j, funcNo) {
                        html += '<td></td>';
                    });
                    if ($("#editAuth").text() == "A" || $("#editAuth").text() == "P") {
                        html += '<td><button type="button" class="btn btn-info btn-sm" onclick="modifySubcon(' + result["subconList"][i]["cno"] + ')"><i class="fa-solid fa-pen-to-square"></i> 편집</button></td>';
                    }
                    html += '</tr>';
                }
            }
            
            $("#tblSubconList tbody").append(html);

            if($("#editAuth").text() == "N") {
                $(".editingPartArea").hide();
                // $("#tblSubconList thead th:last-child").hide();
            }
        }
    });
}

//협력업체 수정 팝업창
function modifySubcon(cno) {
    var jno = $("#jno").val();
    var url = "manage/manage_subcon_view.php?jno=" + jno + "&cno=" + cno;
    var name = "editSubcon";
    var option = "width = 1000, height = 800, top = 100, left = 200, location = no"
    window.open(url, name, option);
}

//첨부파일 선택 시
function onAttachFileChange(obj) {
    var fileName = $(obj).val().split("\\").pop();
    $(obj).siblings(".custom-file-label").addClass("selected").html(fileName);
}

//첨부파일 삭제
function delAttachedFile(obj) {
    var deleteId = $(obj).siblings("input[type='hidden']").val();
    var hidden = '';
    if(deleteId) {
        hidden = '<input type="hidden" name="deleteAttachIdList[]" value="' + deleteId + '" />';
        $("#hiddenDeleteAttach").append(hidden);
    }
    $(obj).closest('div.input-group').remove();
}

//첨부파일 저장 버튼
function onBtnSaveAttachClick() {
    $("#mode").val("SAVE_ATTACH");
    var formdata = new FormData($("#mainForm")[0]);

    $.ajax({ 
        type: "POST", 
        url: "manage/manage_project_detail.php", 
        data: formdata,
        dataType: "json", 
        contentType: false,
        processData: false,
        success: function(result) {
            //첨부파일 다시 불러오기
            importAttachment();
            //삭제 아이디 비우기
            $("#hiddenDeleteAttach").empty();

            $("#modifyAttach").hide();
            $("#showAttach").show();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//첨부파일 불러오기
function importAttachment() {
    $("#mode").val("IMPORT_ATTACH");

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            //첨부파일 목록
            var html = '';
            $(result["attachList"]).each(function(i, info) {
                html += '<div class="input-group mb-2">';
                html += '<div class="custom-file">';
                html += '<label class="custom-file-label" for="customFile">';
                // html += '<a href="/manage/manage_project_attach_download.php?jnoFno='+ info["jnoFno"] +'" />'
                html += '<a href="common/file_download.php?mKind=JOB&fno=' + info["jnoFno"] + '" target="_blank">';
                html += info["fileName"];
                html += '</a>';
                html += '</label>';
                html += '</div>';
                html += '</div>';
            });

            $("#showAttachList").empty().append(html);

            //첨부파일 수정
            html = '';
            var hidden = '';
            $(result["attachList"]).each(function(i, info) {
                html += '<div class="input-group mb-2">';
                html += '<div class="custom-file">';
                html += '<label class="custom-file-label" for="customFile">'+ info["fileName"] +'</label>';
                html += '</div>';
                html += '<div class="input-group-append">';
                html += '<button type="button" class="btn btn-secondary" onclick="javascript:delAttachedFile(this);">&times;</button>';
                html += '<input type="hidden" value="' + info["jnoFno"] + '" />';
                html += '</div>';
                html += '</div>';

                hidden += '<input type="hidden" name="existAttach[]" value="' + info["fileName"] + '"/>';
            });

            $("#modifyAttachList").empty().append(html);
            $("#hiddenExistAttach").empty().append(hidden);

        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//첨부파일 편집 버튼
function onBtnModifyAttachClick() {
    $("#modifyAttach").show();
    $("#showAttach").hide();
}

//관리감독자 체크
// function onSelDisciplineChange() {
//     $("#mode").val("IMPORT_SV");

//     $.ajax({
//         type: "POST",
//         url: "manage/manage_project_detail.php",
//         data: $("#mainForm").serialize(),
//         dataType: "json",
//         success: function(result) {
//             var rows = $("#SVListGrid").jqxGrid('getrows');
            
//             //체크박스 선택
//             $.each(rows, function(index, value) {
//                 $.each(result["supervisorList"], function(i, userInfo) {
//                     if (userInfo["uno"] == value["uno"]) {
//                         $('#SVListGrid').jqxGrid('selectrow', index);
//                     }
//                 });
//             });
//         },
//         beforeSend:function(){
//             $('#SVListGrid').jqxGrid('clearfilters');
//             $("#selSVList").text('');
//             if ($('#SVListGrid').jqxGrid('getselectedrowindex') > -1) {
//                 $('#SVListGrid').jqxGrid('clearselection');
//             }
//         },
//         complete: function() {
//             addfilterSV();
//         },
//         error: function(request, status, error) {
//             alert("code:" + request.status + "\n" + "message:" + request.responseText + "\n" + "error:" + error);
//         }
//     });
// }

//관리감독자 반영 버튼
function onBtnReflectSVSelClick() {
    $("#mode").val("SV_ADD");
    
    var svListGrid = $("#SVListGrid");
    var selSVList = svListGrid.jqxGrid('selectedrowindexes');
    
    $("#hiddenSVList").empty();
    if(validateSelSV() & validateDiscipline()) {
        if(selSVList.length > 0) {
            var hidden = '';
            $.each(selSVList, function(i, row) {
                var rowData = svListGrid.jqxGrid('getrowdata', row);
                
                var uno = rowData["uno"];
                
                hidden += '<input type="hidden" name="selSupervisorList[]" value="' + uno + '" />';
            });
            
            $("#hiddenSVList").append(hidden);
        }
        
        $.ajax({
            type: "POST",
            url: "manage/manage_project_detail.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
    
                if(result["proceed"]) {
                    alert("반영되었습니다.");
                    // $('#SVSelWindow').jqxWindow('close');
                    // importSuperVisor();
                }
                //현장소장 또는 안전관리자와 중복일 경우
                else {
                    alert(result["msg"]);
                }
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//관리감독자 가져오기
function importSuperVisor() {
    $("#mode").val('IMPORT_SV');

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            var html = '';
            var disciplineList = result["disciplineList"];

            //관리감독자 thead
            var disciplineListCnt = disciplineList.length;
            html = '';
            html += '<tr>';
            if($("#editAuth").text() == "A") {
                html += '<th colspan="' + (disciplineListCnt + 2) + '">관리감독자';
                html += '<button type="button" id="btnEditSV" class="btn btn-info btn-sm editingArea" style="float:right"><i class="fa-solid fa-pen-to-square"></i> 공종(현장) 편집</button>';
                html += '<button type="button" id="btnSelSV" class="btn btn-info btn-sm editingArea mr-2" style="float:right"><i class="fa-solid fa-plus"></i> 추가</button>';
                html += '<button type="button" id="btnSaveSV" class="btn btn-info btn-sm editingArea" style="float:right;display:none"><i class="fa-solid fa-floppy-disk"></i></i> 공종(현장) 저장</button>';
                html += '<button type="button" id="btnCancelSV" class="btn btn-info btn-sm editingArea mr-2" style="float:right;display:none">취소</button>';
            } else {
                html += '<th colspan="' + (disciplineListCnt + 1) + '">관리감독자';
            }
            html += '</th>';
            html += '</tr>';
            html += '<tr>';
            html += '<th rowspan="2">성명 (ID)</th>';
            html += '<th colspan="'+ disciplineListCnt +'">공종(현장) 선택</th>';
            //관리자일 경우 삭제 column 보이기
            if($("#editAuth").text() == "A") {
                html += '<th rowspan="2">삭제</th>';
            }
            html += '</tr>';
            html += '<tr>';
            $(disciplineList).each(function(i, info) {
                html += '<th>';
                html += info["funcName"];
                html += '</th>';
            });

            $("#tblSVList thead").empty().append(html);

            html = '';
            $(result["supervisorList"]).each(function(i, info) {
                //사람별 공종
                var funcNoList = info["funcNo"].split("|");
                html += '<tr>';
                html += '<td>';
                html += info["userName"] + " " + info["dutyName"] + " (" + info["userId"] + ")";
                html += '</td>';
                $.each(disciplineList, function(j, funcNo) {
                    html += '<td class="text-center showFuncSV">';
                    $.each(funcNoList, function(k, selFunc) {
                        if(funcNo["funcNo"] == selFunc) {
                            html += '<i class="fa-solid fa-check"></i>';
                        }
                    });
                    html += '</td>';
                    html += '<td class="editFuncSV" style="display:none">';
                    if(funcNoList.includes(funcNo["funcNo"])) {
                        html += '<input type="checkbox" name="changeFuncSVList[]" value="'+ info["uno"] + "|" + funcNo["funcNo"] +'" checked disabled/>';
                    } else {
                        html += '<input type="checkbox" name="changeFuncSVList[]" value="'+ info["uno"] + "|" + funcNo["funcNo"] +'" disabled/>';
                    }
                    html += '</td>';
                });
                //관리자일 경우 삭제 column 보이기
                if($("#editAuth").text() == "A") {
                    html += '<td>';
                    html += '<span class="btn btn-danger btn-sm" onclick="btnDelSVClick('+ info["uno"] +', \''+ info["funcNo"] +'\')"><i class="fa-solid fa-trash-can"></i></span>';
                    html += '</td>';
                }
            });
            html += '</tr>';
            $("#tblSVList tbody").empty().append(html);
        },
        complete: function() {
            //관리감독자 선택 모달
            SVSelWindow.init();
            //공종(현장) 편집 버튼
            $("#btnEditSV").on('click', onBtnEditSVClick);
            //취소 버튼
            $("#btnCancelSV").on('click', importSuperVisor);
            //공종(현장) 저장 버튼
            $("#btnSaveSV").on('click', onBtnSaveSVClick);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//관리감독자 삭제
function btnDelSVClick(uno, funcNo) {
    if(confirm("삭제하시겠습니까?")) {
        $("#mode").val('DEL_SV');
    
        $("#delUnoSV").val(uno);
        var funcNoList = funcNo.split("|");
        var hidden = '';
        $(funcNoList).each(function(i, funcNo) {
            hidden += '<input type="hidden" name="delSVFuncNoList[]" value="'+ funcNo +'"/>';
        });

        $("#hiddenSVDelFuncNoList").empty().append(hidden);
    
        $.ajax({
            type: "POST",
            url: "manage/manage_project_detail.php",
            data: $("#mainForm").serialize(),
            dataType: "json",
            success: function(result) {
                importSuperVisor();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//안전관리자 팀장 선택
function onBtnSelTeamLeaerClick(uno) {
    $("#mode").val("SEL_LEADER");

    $("#selTeamLeaderUno").val(uno);

    $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            importSMList();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//공종 필수 입력
function validateDiscipline() {
    var isExtDiscipline = $("input[type='checkbox'][name='chkDisciplineList[]']:checked").length;

    if(isExtDiscipline > 0) {
        $("#chkDiscipline").find(".invalid-feedback").html("");
        $("#chkDiscipline").find(".invalid-feedback").hide();

        return true
    } else {
        $("#chkDiscipline").find(".invalid-feedback").html("하나이상의 공종을 선택하세요");
        $("#chkDiscipline").find(".invalid-feedback").show();

        // return false
    }
}

//관리감독자 필수 선택
function validateSelSV() {
    if($("#selUnoSV").val()) {
        $(".selSV").find(".invalid-feedback").html('');
        $(".selSV").find(".invalid-feedback").hide();
        
        return true
    } else {
        $(".selSV").find(".invalid-feedback").html("직원을 선택하세요.");
        $(".selSV").find(".invalid-feedback").show();
    }
}

//공종(현장) 편집 버튼
function onBtnEditSVClick() {
    $("#tblSVList tbody").find("input[type=checkbox]").prop('disabled', false);
    $(".showFuncSV").hide();
    $(".editFuncSV").show();
    $("#btnEditSV").hide();
    $("#btnSelSV").hide();
    $("#btnSaveSV").show();
    $("#btnCancelSV").show();
}

//공종(현장) 저장 버튼
function onBtnSaveSVClick() {
    $("#mode").val("SV_SAVE");

        $.ajax({
        type: "POST",
        url: "manage/manage_project_detail.php",
        data: $("#mainForm").serialize(),
        dataType: "json",
        success: function(result) {
            importSuperVisor();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//JOB 선택
function jobSelected() {
    if ($("#pageId").text() == "project_detail") {
        //프로젝트 상세
        $("<input>").attr({
            type: "hidden",
            id: "page_id",
            name: "page_id",
            value : $("#pageId").text()
        }).appendTo( $("#mainForm") );
        $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
    }
}
</script>

<div class="menu-sticky-top">
<ol class="breadcrumb">
    <li class="breadcrumb-item"></li>
    <li class="breadcrumb-item">프로젝트 상세</li>
</ol>
<div class="btnList">
    <button type="button" class="btn btn-primary" id="btnListProject" style="display: none;"><i class="fa-solid fa-rotate-left"></i>&nbsp;목록</button>
</div>
</div>

<span style="display: none;" id="pageId"></span>
<div id="divNone">
    선택된 JOB이 없습니다.
</div>
<div id="divProjectDetail">
<form id="mainForm" name="mainForm" method="post" enctype="multipart/form-data">
<div class="container-fluid">
    <div class="row">
        <div class="col-7">
            <label class="control-label"><i class="fa-solid fa-dice-d6"></i><b> JOB정보</b></label>
            <table class="table table-bordered table-sm" id="tblJobDetail" name="tblJobDetail">
                <tbody>
                </tbody>
            </table>
        </div>
        <div class="col-5">
            <br />
            <label class="control-label"><i class="fa-solid fa-dice-d6"></i><b> 첨부파일</b></label>
            <span id="modifyAttach" style="display:none;">
                <span style="float:right">
                    <a class="btn btn-info btn-sm ml-2" id="btnSaveAttach" style="text-align:right">첨부파일저장</a>
                </span>
                <span style="float:right">
                    <a class="btn btn-warning btn-sm" id="btnPlusAttach" onclick="onBtnPlusAttachClick('modifyAttachList')" style="text-align:right"><i class="fa-solid fa-plus"></i></a>
                </span>
                <div id="modifyAttachList"></div>
            </span>
            <span id="showAttach" class="editingPartArea" style="display: none;">
                <span style="float:right">
                    <a class="btn btn-info btn-sm" id="btnModifyAttach" style="text-align:right">첨부파일편집</a>
                </span>
                <div id="showAttachList"></div>
            </span>
        </div>
    </div>
    <br />
    <label><i class="fa-solid fa-dice-d6"></i><b> 결재라인</b></label>
    <div class="row">
        <div class="col-2">
            <table class="table table-bordered" id="tblSuperIntendent" name="tblSuperIntendent">
                <thead>
                    <tr>
                        <th>현장소장</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div class="col-3">
            <table class="table table-bordered" id="tblSMList" name="tblSMList">
                <thead>
                    <tr>
                        <th>안전관리자<button type="button" id="btnSelSM" class="btn btn-info btn-sm editingArea" style="float:right; display: none;"><i class="fa-solid fa-pen-to-square"></i> 편집</button></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div class="col-7">
            <table class="table table-bordered" id="tblSVList" name="tblSVList">
                <thead></thead> 
                <tbody></tbody>
            </table>
        </div>
    </div>
    <br />
    <div class="row">
        <div id="divSubconList" class="col">
            <label class="control-label requiredvalue"><i class="fa-solid fa-dice-d6"></i><b> 협력업체</b></label>
            <table class="table table-bordered" id="tblSubconList">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<!-- 안전관리자 선택 모달 -->
<div id="jqxWidget" style="display:none">
    <div id="mainDemoContainer">
        <div id="SMSelWindow">
            <div id="SMSelWindowHeader">
                <span>안전관리자 선택</span>
            </div>
            <div id="SMSelWindowContent">
                <div id="selSMList" class="container-fluid border p-1 m-1" style="min-height: 50px;width:98.5%"></div>
                <div id="SMListGrid"></div>
                <br />
                <div class="d-flex justify-content-around">
                    <button type="button" id="btnReflectSMSel" class="btn btn-primary">반영</button>
                    <button type="button" id="closeSMSel" class="btn btn-secondary">닫기</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- 관리감독자 선택 모달 -->
<div id="jqxWidget" style="display:none">
    <div id="mainDemoContainer">
        <div id="SVSelWindow">
            <div id="SVSelWindowHeader">
                <span>관리감독자 선택</span>
            </div>
            <div id="SVSelWindowContent">
                <div class="form-group selSV">
                    <i class="fa-solid fa-dice-d6"></i> 직원 선택
                    <div id="jqxdropdownbutton">
                        <div style="border-color: transparent;" id="SVListGrid">
                        </div>
                    </div>
                    <div class="invalid-feedback"></div>
                </div>
                    <br />
                    <div>
                    <i class="fa-solid fa-dice-d6"></i> 공종(현장) 선택
                    <!-- <select class="form-control" id="selDiscipline" name="selDiscipline" onchange="onSelDisciplineChange()" form="mainForm">
                    </select> -->
                    <div class="form-group container border p-2" id="chkDiscipline"></div>
                    <br />
                </div>
                <br />
                <div class="d-flex justify-content-around">
                    <button type="button" id="btnReflectSVSel" class="btn btn-primary">반영</button>
                    <button type="button" id="closeSVSel" class="btn btn-secondary">닫기</button>
                </div>
            </div>
        </div>
    </div>
</div>

<span id="editAuth" style="display:none"></span>
<div id="hiddenDeleteAttach"></div>
<div id="hiddenExistAttach"></div>
<div id="hiddenSMList"></div>
<div id="hiddenSVList"></div>
<div id="hiddenSVDelFuncNoList"></div>
<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="jno" name="jno" />
<input type="hidden" id="delUnoSV" name="delUnoSV" />
<input type="hidden" id="selUnoSV" name="selUnoSV" />
<input type="hidden" id="selTeamLeaderUno" name="selTeamLeaderUno" />
<input type="hidden" id="teamLeaderUno" name="teamLeaderUno" />
</form>

</div>

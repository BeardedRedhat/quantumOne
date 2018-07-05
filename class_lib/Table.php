<?php

// Paginated table class - created and owned by ISArc Ltd.
// Permitted for use by Richard Taylor (MD) for this project
class Table {

    public static function render($query, $options = '{}') {
        $options = json_decode($options, true);

        if($options == null) {
            throw new Exception('Invalid options provided on page ' . $_SERVER['REQUEST_URI']);
        }

        $tableName = array_key_exists('tableName', $options) ? ($options['tableName']) : '';

        if(isset($_POST['datasourceForTable']) && $_POST['datasourceForTable'] != $tableName) {
            return '';
        }

        //Check for the show header flag, defaulting to true if not present.
        $showHeader = array_key_exists('showHeader', $options) ? $options['showHeader'] : true;

        //Calculate sorting options
        if($showHeader && array_key_exists('sorting', $options) && $options['sorting'] == true) {
            if(isset($_post['newSort'])) {
                $currentSort = $_POST['newSort'];
            } else {
                if(isset($_POST['currentSort'])) {
                    $currentSort = $_POST['currentSort'];
                } else {
                    $currentSort = '';
                }
            }

            $sort = '<input type="hidden" id="hdnSort_' . $tableName . '" name="hdnSort_' . $tableName . '" value="' . $currentSort . '" />';
            $sort .= '<script type="text/javascript">';
            $sort .= 'function sort_' . $tableName . '(dest, sort) {';
            $sort .= "var tbl = $('#' + dest);";
            $sort .= "var cover = $('<div></div>')
                                            .attr('id','cover')
                                            .css('height', tbl.height() + 'px')
                                            .css('width', tbl.width() + 'px')
                                            .css('background-color','rgba(0,0,0,0.3)')
                                            .css('position','absolute')
                                            .css('top','0px')
                                            .css('left', '0px')
                                            .css('text-align', 'center')
                                            .css('line-height', tbl.height() + 'px');";
            $sort .= "cover.append($('<span></span>')
                                            .html('Please wait, loading...')
                                            .css('background-color','white')
                                            .css('padding','20px')
                                            .css('border', '1px solid black')
                                            .css('border-radius', '15px')
                                            .css('font-size', '1.5em'));";
            $sort .= "tbl.append(cover);";
            $sort .= "$('#hdnSort_$tableName').val(sort);";
            $sort .= "setTimeout(function() { $.post('" . $_SERVER['REQUEST_URI'] . "', {'datasourceForTable':'$tableName', '':'', 'numberOfPages':$(\"#hdnNumberOfPages_$tableName\").val(), 'currentPage':$(\"#hdnCurrentPage_$tableName\").val(), 'currentSort':$(\"#hdnSort_$tableName\").val() }, ";
            $sort .= "function(data) { console.log(data); var newPage = $.parseHTML(data); tbl.empty().append($(newPage).children()).find('#cover').remove(); });";
            $sort .= "}, 1);";
            $sort .= '}';
            $sort .= '</script>';
        }

        $db = new Database();
        $link = $db -> openConnection();

        //Calculate paging information
        if(array_key_exists('paging', $options) && array_key_exists('pageSize', $options) && $options['paging']) {
            //Get the page size from options
            $pageSize = $options['pageSize'];

            //Get total number of rows returned by query, is this is a post from the pager then collect the information from the hidden field.
            if(isset($_POST['numberOfPages'])) {
                $numberOfPages = $_POST['numberOfPages'];
            } else {
                $rowCountQuery = "SELECT COUNT('') AS RowCount FROM (" . $query . ") AS Query";
                $rowCount = $link -> prepare($rowCountQuery);
                $rowCount -> setFetchMode(PDO::FETCH_ASSOC);
                $rowCount -> execute();
                $rowCount = $rowCount -> fetch();
                $rowCount = $rowCount['RowCount'];

                //Get the number of pages required to display the returned results.
                $numberOfPages = floor($rowCount / $pageSize);
                if(($rowCount % $pageSize) > 0) $numberOfPages += 1;
            }

            if($numberOfPages > 1) {
                if(isset($_POST['currentPage'])) {
                    $currentPage = $_POST['currentPage'];
                } else {
                    $currentPage = 1;
                }

                $pager = '<ul class="pagination" style="margin: 0px 0px;">';

                if($currentPage == 1) {
                    $pager .= '<li class="disabled"><a style="min-width: 42px; text-align: center;">&#65513;</a></li>';
                } else {
                    $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', ' . ($currentPage - 1) . ');" style="min-width: 42px; text-align: center;">&#65513;</a></li>';
                }

                if($numberOfPages <= 9) {
                    for($pageNumber=1; $pageNumber <= $numberOfPages; $pageNumber++) {
                        if($pageNumber == $currentPage) {
                            $pager .= '<li class="active"><a>' . $pageNumber . '</a></li>';
                        } else {
                            $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', ' . $pageNumber . ');">' . $pageNumber . '</a></li>';
                        }
                    }
                } else {
                    //Calculate first page on pager.
                    $firstPage = $currentPage - 4;
                    if($firstPage < 1) $firstPage = 1;

                    //Calculate last page on pager.
                    $lastPage = $firstPage + 8;
                    if($lastPage > $numberOfPages) $lastPage = $numberOfPages;

                    //Pad the pager to ensure showing 9 pages at any time.
                    while($lastPage - $firstPage < 8) {
                        $firstPage -= 1;
                    }

                    if($firstPage > 1) $firstPage += 2;
                    if($lastPage < $numberOfPages) $lastPage -= 2;

                    if($firstPage > 1) {
                        $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', 1);" style="min-width: 42px; text-align: center;">1</a></li>';
                        $pager .= '<li class="disabled"><a style="min-width: 42px; text-align: center;">...</a></li>';
                    }

                    for($pageNumber=$firstPage; $pageNumber <= $lastPage; $pageNumber++) {
                        if($pageNumber == $currentPage) {
                            $pager .= '<li class="active"><a style="min-width: 42px; text-align: center;">' . $pageNumber . '</a></li>';
                        } else {
                            $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', ' . $pageNumber . ');" style="min-width: 42px; text-align: center;">' . $pageNumber . '</a></li>';
                        }
                    }

                    if($lastPage < $numberOfPages) {
                        $pager .= '<li class="disabled"><a style="min-width: 42px; text-align: center;">...</a></li>';
                        $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', ' . $numberOfPages . ');" style="min-width: 42px; text-align: center;">' . $numberOfPages . '</a></li>';
                    }
                }

                if($currentPage == $numberOfPages) {
                    $pager .= '<li class="disabled"><a style="min-width: 42px; text-align: center;">&#65515;</a></li>';
                } else {
                    $pager .= '<li><a href="javascript:pageChange_' . $tableName . '(\'' . $tableName . '\', ' . ($currentPage + 1) . ');" style="min-width: 42px; text-align: center;">&#65515;</a></li>';
                }

                $pager .= '</ul>';
                $pager .= '<input type="hidden" id="hdnCurrentPage_' . $tableName . '" name="hdnCurrentPage_' . $tableName . '" value="' . $currentPage . '" />';
                $pager .= '<input type="hidden" id="hdnNumberOfPages_' . $tableName . '" name="hdnNumberOfPages_' . $tableName . '" value="' . $numberOfPages . '" />';

                //Create jquery required for callback paging.
                $pager .= "<script type=\"text/javascript\">";
                $pager .= "function pageChange_$tableName(dest, page) {";
                $pager .= "var tbl = $('#' + dest);";
                $pager .= "var cover = $('<div></div>')
                                            .attr('id','cover')
                                            .css('height', tbl.height() + 'px')
                                            .css('width', tbl.width() + 'px')
                                            .css('background-color','rgba(0,0,0,0.3)')
                                            .css('position','absolute')
                                            .css('top','0px')
                                            .css('left', '0px')
                                            .css('text-align', 'center')
                                            .css('line-height', tbl.height() + 'px');";
                $pager .= "cover.append($('<span></span>')
                                            .html('Please wait, loading...')
                                            .css('background-color','white')
                                            .css('padding','20px')
                                            .css('border', '1px solid black')
                                            .css('border-radius', '15px')
                                            .css('font-size', '1.5em'));";
                $pager .= "tbl.append(cover);";
                $pager .= "$('#hdnCurrentPage_$tableName').val(page);";
                $pager .= "setTimeout(function() { $.post('" . $_SERVER['REQUEST_URI'] . "', {'datasourceForTable':'$tableName', '':'', 'numberOfPages':$(\"#hdnNumberOfPages_$tableName\").val(), 'currentPage':$(\"#hdnCurrentPage_$tableName\").val(), 'currentSort':$(\"#hdnSort_$tableName\").val() }, ";
                $pager .= "function(data) { var newPage = $.parseHTML(data); tbl.empty().append($(newPage).children()).find('#cover').remove(); });";
                $pager .= "}, 1);";
                $pager .= "}";
                $pager .= "</script>";

                $limit = " LIMIT " . ($currentPage - 1) * $pageSize . ", $pageSize";
            }
        }

        $query = "SELECT * FROM (" . $query . ") AS RawData";
        if(isset($sort) && !empty($currentSort)) {
            $query .= " ORDER BY " . $currentSort;
        }
        if(isset($limit)) {
            $query .= $limit;
        }

        //Run the query
        $dsResults = $link -> query($query);

        //If an error occurs running the query then return an error message.
        if(!$dsResults) return 'An unexpected error has occurred while attempting to create a table for display.'
        . '<br />Error (' . $link -> errorInfo()[1] . ') - ' . $link -> errorInfo()[2];

        $link = null;

        //If no results are returned then return an empty data message.
        if($dsResults -> rowCount() == 0) return "<div class=\"table-no-results\" style=\"margin-bottom:15px;\">".(array_key_exists('emptyDataText', $options) ? $options['emptyDataText'] : 'There is no information to show.')."</div>";

        $table = '<table id="' . $tableName . '" class="table table-striped'.(array_key_exists('rowURL', $options) ? " table-hover" : "").'" style="border-bottom: solid 1px #d1d1d1; position: relative;"><tbody>';

        if(array_key_exists('rowURL', $options)){
            $queryString = $options['rowURL'];
            if(array_key_exists('rowURLQS', $options)){
                $count = 0;
                foreach($options['rowURLQS'] as $id => $field){
                    if($count == 0){
                        $queryString .= "?".$id."=%s";
                    }
                    else{
                        $queryString .= "&".$id."=%s";
                    }
                    $count++;
                }
            }
        }

        if(!function_exists('createTableHeaderCell')) {
            function createTableHeaderCell($tableName, $headerText, $currentSort = null, $fieldName = null) {
                $headerText = Text::splitPascalCase(isset($fieldName) ? $fieldName : $headerText);

                if($fieldName != null && is_string($currentSort)) {
                    return '<th><a href="javascript: sort_' . $tableName . '(\'' . $tableName . '\', \'' . $fieldName . ($currentSort == $fieldName . ' ASC' ? ' DESC' : ' ASC') . '\');">' . $headerText . '</a></th>';
                } else {
                    return '<th>' . $headerText . '</th>';
                }
            }
        }

        $dsResults -> setFetchMode(PDO::FETCH_ASSOC);
        while($row = $dsResults -> fetch()) {
            //********** Render the header **********
            if($showHeader) {
                $table .= '<tr>';
                if(array_key_exists('columns', $options)) {
                    foreach($options['columns'] as $col) {
                        if(gettype($col) == 'string') {
                            $table .= createTableHeaderCell($tableName, $col, (isset($sort) ? $currentSort : null), $col);
                        } else {
                            $table .= createTableHeaderCell($tableName, $col['header'], (isset($sort) ? $currentSort : null), null);
                        }
                    }
                } else {
                    foreach($row as $col => $value) {
                        $table .= createTableHeaderCell($tableName, $col, (isset($sort) ? $currentSort : null), $col);
                    }
                }
                $table .= '</tr>';

                $showHeader = false;
            }
            //********** Render the header **********

            if(array_key_exists('rowURLQS', $options)){
                $qsFields = array();

                foreach($options['rowURLQS'] as $field){
                    if(isset($row[$field])){
                        $qsFields[] = Crypt::encrypt($row[$field]);
                    }
                    else{
                        $qsFields[] = Crypt::encrypt($field);
                    }
                }
                $newQueryString = vsprintf($queryString, $qsFields);
            }

            $table .= '<tr>';
            if(array_key_exists('columns', $options)) {
                foreach($options['columns'] as $value) {
                    if(gettype($value) == 'string') {
                        if($date = DateTime::createFromFormat('Y-m-d', $row[$value])) {
                            $row[$value] = $date -> format('d/m/Y');
                        }

                        if(array_key_exists('rowURL', $options)){
                            $table .= '<td data-href="'.$newQueryString.'">' . $row[$value] . '</td>';
                        } else {
                            $table .= '<td>' . $row[$value] . '</td>';
                        }
                    } else {
                        $fields = array();

                        foreach($value['fields'] as $field) {
                            if(array_key_exists('encrypt', $field) && $field['encrypt']) {
                                $fields[] = Crypt::encrypt($row[$field['fieldName']]);
                            } else {
                                $fields[] = $row[$field['fieldName']];
                            }
                        }

                        if(array_key_exists('rowURL', $options)){
                            $table .= '<td data-href="'.$newQueryString.'">' . vsprintf($value['html'], $fields) . '</td>';
                        }
                        else{
                            $table .= '<td>' . vsprintf($value['html'], $fields) . '</td>';
                        }
                    }
                }
            } else {
                foreach($row as $value) {
                    if(array_key_exists('rowURL', $options)){
                        $table .= '<td data-href="'.$newQueryString.'">' . $value . '</td>';
                    }
                    else{
                        $table .= '<td>' . $value . '</td>';
                    }
                }
            }
            $table .= '</tr>';
            $columnCount = count($row);
        }

        if(isset($pager)) {
            if(array_key_exists('columns', $options)) {
                $table .= '<tr><td colspan="' . count($options['columns']) . '">' . $pager .'</td></tr>';
            }
            else{
                $table .= '<tr><td colspan="' . $columnCount . '">' . $pager .'</td></tr>';
            }
        }

        $table .= '</tbody></table>';

        if(isset($sort)) {
            $table .= $sort;
        }

        if(isset($_POST['datasourceForTable']) && $_POST['datasourceForTable'] == $tableName) {
            die($table);
        } else {
            return $table;
        }
    }
}
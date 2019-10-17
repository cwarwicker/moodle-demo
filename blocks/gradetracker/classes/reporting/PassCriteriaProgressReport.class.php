<?php

namespace GT\Reports;

require_once 'Report.class.php';

/**
 * Description of PassCriteriaProgressReport
 *
 * @author cwarwicker
 */
class PassCriteriaProgressReport extends \GT\Reports\Report {
    
    protected $name = 'PASS_CRIT_PROGRESS';
    
    public function run(array $params) {
        
        global $CFG, $USER;
        
        $User = new \GT\User($USER->id);
        $params = (isset($params['params'])) ? $params['params'] : false;
        
        // Make sure qual structure has been passed through
        $qualStructure = false;
        $structureID = $this->extractParam("structureID", $params);
        if ($structureID != 'all'){
            $qualStructure = new \GT\QualificationStructure($structureID);
            if (!$qualStructure->isValid()){
                gt_ajax_progress(false, array(
                    'error' => get_string('blockbcgtdata:err:invalidqualstructure', 'block_gradetracker')
                ));
                return false;
            }
        }
        
        // Make sure at least 1 course category selected
        $cats = $this->extractParam("categories", $params);
        if (!is_array($cats) || empty($cats)){
            gt_ajax_progress(false, array(
                'error' => get_string('invalidcoursecat', 'block_gradetracker')
            ));
            return false;
        }
        
        // Are we looking for any specific criteria awards as well?
        $specificAwards = $this->extractParam('extraAwardNames', $params); 
        
        // Setup the PHPExcel object
        require_once $CFG->dirroot . '/lib/phpexcel/PHPExcel.php';
        
        // Setup Spreadsheet
        $precision = ini_get('precision');
        $objPHPExcel = new \PHPExcel();
        ini_set('precision', $precision); # PHPExcel fucks up the native round() function by changing the precision
        
        $objPHPExcel->getProperties()
                     ->setCreator($User->getDisplayName())
                     ->setLastModifiedBy($User->getDisplayName())
                     ->setTitle( get_string('reports:passprog', 'block_gradetracker') )
                     ->setSubject( get_string('reports:passprog', 'block_gradetracker') )
                     ->setDescription( get_string('reports:passprog', 'block_gradetracker') . " " . get_string('generatedbygt', 'block_gradetracker'))
                     ->setCustomProperty( "GT-REPORT" , $this->name, 's');


        // Remove default sheet
        $objPHPExcel->removeSheetByIndex(0);
        
        $styles = array(
            'centre' => array(
                'alignment' => array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                )
            ), 
            'qual' => array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => '538DD5')
                ),
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => 'ffffff')
                )
            ),
            'course' => array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'ffff33')
                ),
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => '000000')
                )
            ),
            'totals' => array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'B1A0C7')
                ),
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => 'ffffff')
                )
            ),
            'max' => array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => '948A54')
                ),
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => 'ffffff')
                )
            )
            
        );
        
        
        $GTEXE = \GT\Execution::getInstance();
        $GTEXE->COURSE_CAT_MIN_LOAD = true;
        $GTEXE->QUAL_MIN_LOAD = true;
        
        $structures = array();
        $structureSettings = array();
        
        $catArray = array();
        $total = 0;
        $usedFieldNames = array();
        
        // Calculate how many courses we are going to need to process, so we can do a progress bar
        if ($cats)
        {
            foreach($cats as $catID)
            {
                
                $Cat = new \GT\CourseCategory($catID);                
                $Cat->convertCoursesToFlatArray();
                $Cat->filterOutCoursesWithoutQualifications();
                $catArray[$catID] = $Cat;
                $total += count($Cat->getCourses());
                
            }
        }
        
        // Okay, now we know how many courses we are going to need to process, let's start processing!
        $done = 0;
        $sheetIndex = 0;
                
        if ($catArray)
        {
            foreach($catArray as $cat)
            {
                
                // Create a sheet for this category
                $objPHPExcel->createSheet($sheetIndex);
                $objPHPExcel->setActiveSheetIndex($sheetIndex);
                $objPHPExcel->getActiveSheet()->setTitle( $this->convertStringToWorksheetName($cat->name) );
                
                $sheetIndex++;
                $row = 1;
                
                $courses = $cat->getCourses();
                if ($courses)
                {
                    foreach($courses as $course)
                    {
                                                
                        // Course name
                        $objPHPExcel->getActiveSheet()->setCellValue("A{$row}", $course->getNameWithCategory());                    
                        $objPHPExcel->getActiveSheet()->mergeCells("A{$row}:C{$row}");

                        $courseRow = $row;
                        $row++;
                        
                        // Get qualifications on course
                        $quals = $course->getCourseQualifications();
                        if ($quals)
                        {
                            
                            foreach($quals as $qual)
                            {
                                
                                // If we chose a specific qual structure, make sure this qual is that one, otherwise skip
                                if ($qualStructure && $qual->getStructureID() <> $qualStructure->getID()){
                                    continue;
                                }
                                
                                
                                if (array_key_exists($qual->getStructureID(), $structures)){
                                    
                                    $structure = $structures[$qual->getStructureID()];
                                    $method = $structureSettings[$qual->getStructureID()]['pass_method'];
                                    $methodValue = $structureSettings[$qual->getStructureID()]['pass_method_value'];
                                    $view = $structureSettings[$qual->getStructureID()]['view'];
                                    $nameWeightings = $structureSettings[$qual->getStructureID()]['weightings'];
                                    
                                } else {
                                    
                                    $structure = $qual->getStructure();
                                    $method = $structure->getSetting('reporting_pass_criteria_method');
                                    $methodValue = $structure->getSetting('reporting_pass_criteria_method_value');
                                    $view = $structure->getSetting('custom_dashboard_view');
                                    $nameWeightings = json_decode($structure->getSetting('reporting_short_criteria_weighted_scores'));
                                                                    
                                    $structures[$qual->getStructureID()] = $structure;
                                    $structureSettings[$qual->getStructureID()]['view'] = $view;
                                    $structureSettings[$qual->getStructureID()]['weightings'] = $nameWeightings;
                                    $structureSettings[$qual->getStructureID()]['pass_method'] = $method;
                                    $structureSettings[$qual->getStructureID()]['pass_method_value'] = $methodValue;
                                    
                                }
                                
                                // Make sure this structure hasn't set to disabled
                                if ($method === false || is_null($method)){
                                    continue;
                                }
                                                                
                                $studentWeightings = array();
                                
                                
                                // Row for Qual name and Criteria headers
                                
                                // Qual Name
                                $objPHPExcel->getActiveSheet()->setCellValue("A{$row}", $qual->getDisplayName());                    
                                $objPHPExcel->getActiveSheet()->mergeCells("A{$row}:C{$row}");
                                $letter = 'D';
                                
                                // Target grade
                                if ($qual->isFeatureEnabledByName('targetgrades'))
                                {
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('targetgrade', 'block_gradetracker')); 
                                    $letter++;
                                }
                                
                                // Reset from qual to qual
                                $shortCriteriaNames = false;
                                
                                // Short Criteria Names (if enabled)
                                if ($view == 'view-criteria-short')
                                {

                                    // Columns for criteria
                                    $shortCriteriaNames = $qual->getHeaderCriteriaNamesShort();
                                    if ($shortCriteriaNames)
                                    {
                                        foreach($shortCriteriaNames as $crit)
                                        {
                                            $oldLetter = $letter;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $crit);
                                            $letter++;
                                            
                                            // Centre align
                                            $objPHPExcel->getActiveSheet()->getStyle("{$oldLetter}{$row}")->applyFromArray($styles['centre']);

                                        }
                                    }
                                    
                                    // Total weighted scores - adding up the weighted scores of each crit
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('weighting', 'block_gradetracker'));
                                    $letter++;
                                    
                                    // If we are doing it by grading structure, we want that Pass Criteria column here as well
                                    if ($method == 'bygradestructure')
                                    {
                                        // Total pass criteria
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('passcriteria', 'block_gradetracker'));
                                        $letter++;
                                    }
                                    
                                    // All - Best - Total weighted score achieved
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('reports:passprog:header:weightedscoreachieved', 'block_gradetracker'));
                                    $objPHPExcel->getActiveSheet()->getComment("{$letter}{$row}")->getText()->createTextRun('Student Weighting / Max Weighting');
                                    $letter++;
                                    
                                                                        
                                }
                                else
                                {
                                    
                                    // If we are not using short criteria names, then display 2 columns:
                                    
                                    // Total pass criteria
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('passcriteria', 'block_gradetracker'));
                                    $letter++;
                                    
                                    // Total criteria
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('criteria', 'block_gradetracker'));
                                    $letter++;
                                    
                                }
                                
                                
                                // Total % score achieved (pass criteria only) - Out of the max achieved by anyone on the qual
                                $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('reports:passprog:header:passcritachieved', 'block_gradetracker'));
                                $objPHPExcel->getActiveSheet()->getComment("{$letter}{$row}")->getText()->createTextRun('Student "Pass" No. / Max "Pass" No.');
                                $letter++;

                                // Total % score achieved (pass criteria only) - Out of the total on the qual
                                $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", get_string('reports:passprog:header:passcritachievedtotal', 'block_gradetracker'));
                                $objPHPExcel->getActiveSheet()->getComment("{$letter}{$row}")->getText()->createTextRun('Student "Pass" No. / Total "Pass" No.');
                               

                                // Style
                                $objPHPExcel->getActiveSheet()->getStyle("A{$row}:{$letter}{$row}")->applyFromArray($styles['qual']);        
                                $objPHPExcel->getActiveSheet()->getStyle("A{$courseRow}:{$letter}{$courseRow}")->applyFromArray($styles['course']);        
                                                          
                                
                                $row++;
                                
                                // Invalid method
                                if ($method == 'byletter' && !$shortCriteriaNames){
                                    $method = 'all';
                                }
                                            
                                
                                $dataQualification = new \GT\Qualification\DataQualification($qual->getID());
                                $data = $dataQualification->getQualificationReportStudents(false, $view, false, $shortCriteriaNames, $course->id, $specificAwards);
                                                                
                                if ($data)
                                {
                                                       
                                    // Totals row instance
                                    $totalsRow = $row;
                                    $row++;
                                    // End of Totals row instance
                                    

                                    
                                    
                                    
                                    
                                    // Maximums row & some variables
                                    $maxRow = $row;
                                    $max = array();
                                    $maxWeightedScore = 0; # Score
                                    $critTotals = array();
                                    $qualTotalWeightingCell = false;
                                    $qualMaxWeightingCell = false;
                                    $gradingStructureCriteriaCountArray = array();
                                    
                                    if ($qual->isFeatureEnabledByName('targetgrades')){
                                        $letter = 'E';
                                    } else {
                                        $letter = 'D';
                                    }
                                    
                                    $startingLetter = $letter;

                                    // Short criteria names (if enabled)
                                    if ($shortCriteriaNames)
                                    {
                                        foreach($shortCriteriaNames as $crit)
                                        {

                                            // Get the field name used in the query
                                            $fieldName = 'critawardcnt_'.\gt_make_db_field_safe($crit, $usedFieldNames);

                                            // Get the highest value of this field in the results data
                                            $max[$crit] = $this->getMaxValue($fieldName, $data);
                                            $critWeighting = $this->getCriteriaNameWeighting($crit, $nameWeightings);
                                            $maxWeightedScore += ($critWeighting * $max[$crit]);

                                            // Set into worksheet
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $max[$crit]);  
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                            $letter++;

                                        }
                                        
                                        // Maximum weighting, based on the maximum of each of those criteria
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $maxWeightedScore); 
                                        
                                        // Style the maximums row
                                        $objPHPExcel->getActiveSheet()->getStyle("{$startingLetter}{$row}:{$letter}{$row}")->applyFromArray($styles['max']);        
                                        
                                        $qualMaxWeightingCell = array($letter, $row);
                                        
                                        $letter++;
                                        
                                    }
                                    
                                    if (!$shortCriteriaNames || $method == 'bygradestructure')
                                    {
                                        
                                        // Pass Criteria - Get the max any student has for Pass criteria achieved
                                        $passCriteriaCellLetter = $letter;
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", 'maxPass to be overwritten');  
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->applyFromArray($styles['max']);        
                                        
                                        $qualMaxWeightingCell = array($letter, $row);
                                        
                                    }   
                                        
                                    if (!$shortCriteriaNames)
                                    {
                                        $letter++;
                                        // Total Criteria - Get the max any student has for total criteria achieved
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}",  $this->getMaxValue('critawardcnt_all', $data) );  
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                        
                                        // Style the maximums row
                                        $objPHPExcel->getActiveSheet()->getStyle("{$startingLetter}{$row}:{$letter}{$row}")->applyFromArray($styles['max']);        

                                        $qualMaxWeightingCell = array($letter, $row);
                                        
                                    }
                                    
                                    // End of Maximums Row

                                    


                                    
                                    
                                    
                                    // Student rows
                                    $row++;
                                    $startStudentRow = $row;
                                    foreach($data as $student)
                                    {
                                        
                                        // Student object
                                        $studentObj = new \GT\User($student->id);
                                        
                                        // Weighted variables for counting
                                        $studentWeightingMax = 0;
                                        $studentWeighting = 0;

                                        // Name
                                        $objPHPExcel->getActiveSheet()->setCellValue("A{$row}", $student->firstname);  
                                        $objPHPExcel->getActiveSheet()->setCellValue("B{$row}", $student->lastname);  
                                        $objPHPExcel->getActiveSheet()->setCellValue("C{$row}", $student->username);  

                                        $letter = 'D';
                                        
                                        // Target grade
                                        if ($qual->isFeatureEnabledByName('targetgrades')){
                                            $grade = $studentObj->getUserGrade('target', array('qualID' => $qual->getID()));
                                            if (!$grade){
                                                $grade = '';
                                            }
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $grade);  
                                            $letter++;
                                        }
                                        
                                        // Criteria 
                                        if ($shortCriteriaNames)
                                        {
                                            foreach($shortCriteriaNames as $crit)
                                            {

                                                $fieldName = 'critawardcnt_'.\gt_make_db_field_safe($crit, $usedFieldNames);
                                                $fieldNameTtl = 'critcnt_'.\gt_make_db_field_safe($crit, $usedFieldNames);

                                                $met = (int)$student->$fieldName;
                                                $critTotal = (int)$student->$fieldNameTtl;

                                                if (!array_key_exists($crit, $critTotals)){
                                                    $critTotals[$crit] = 0;
                                                }

                                                $critTotals[$crit] += $critTotal;

                                                $weighting = $this->getCriteriaNameWeighting($crit, $nameWeightings);
                                                $studentWeightingMax += ( $critTotal * $weighting );
                                                $studentWeighting += ( $met * $weighting );

                                                $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $met);
                                                $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                                $letter++;

                                            }
                                            
                                            // Total student weighting
                                            $studentWeightings[$student->id] = $studentWeighting;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $studentWeighting);
                                            $letter++;
                                            
                                        }
                                        
                                        if (!$shortCriteriaNames || $method == 'bygradestructure')
                                        {
                                            // Pass Criteria - To be overwritten later
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", 'studPass to be overwritten');
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                            $letter++;
                                        }
                                        
                                        if (!$shortCriteriaNames)
                                        {
                                            // Count up total criteria student is on
                                            if (!array_key_exists('all', $critTotals)){
                                                $critTotals['all'] = 0;
                                            }
                                            
                                            $critTotals['all'] += $student->critcnt_all;
                                            
                                            

                                            // Total Criteria
                                            $met = (int)$student->critawardcnt_all;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$row}", $met);
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$row}")->getFont()->setBold(true);
                                            $letter++;
                                            
                                        }
                                        
                                        $studentRowCalcCell = $letter;
                                        $row++;

                                    }
                                    // End of student row
                                    
                                    
                                    
                                    
                                    // Back to the Totals row
                                    // Now that we've added up the totals for all the students, get an avg
                                    $cntStudents = count($data);
                                    $totalWeighting = 0;
                                    
                                    if ($qual->isFeatureEnabledByName('targetgrades')){
                                        $letter = 'E';
                                    } else {
                                        $letter = 'D';
                                    }
                                    
                                    $startingLetter = $letter;
                                    
                                    if ($shortCriteriaNames)
                                    {
                                        foreach($shortCriteriaNames as $crit)
                                        {
                                            
                                            // It does an average because there is no "total" number of criteria on the qual
                                            // As students may be on differrent combinations of units, with different numbers of criteria
                                            $avgTotal = ceil( @($critTotals[$crit] / $cntStudents) );
                                            $weighting = $this->getCriteriaNameWeighting($crit, $nameWeightings);
                                            $totalWeighting += ($weighting * $avgTotal);

                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$totalsRow}", $avgTotal);
                                            $letter++;
                                        }
                                        
                                        // Total weighting
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$totalsRow}", $totalWeighting);
                                        $qualTotalWeightingCell = "{$letter}{$totalsRow}";

                                        // Style totals row
                                        $objPHPExcel->getActiveSheet()->getStyle("{$startingLetter}{$totalsRow}:{$letter}{$totalsRow}")->applyFromArray($styles['totals']);        
                                        
                                        $letter++;
                                        
                                    }
                                    
                                    if (!$shortCriteriaNames || $method == 'bygradestructure')
                                    {
                                        // To be overwritten later
                                        $totalPassCriteriaCell = "{$letter}{$totalsRow}";
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$totalsRow}", 'ttlPass to be overwritten');  
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$totalsRow}")->getFont()->setBold(true);
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$totalsRow}")->applyFromArray($styles['totals']);        
                                        $letter++;
                                    }
                                    
                                    if (!$shortCriteriaNames)
                                    {
                                                                                
                                        // Total Criteria
                                        $avgTotal = ceil( @($critTotals['all'] / $cntStudents) );
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$totalsRow}", $avgTotal);  
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$totalsRow}")->getFont()->setBold(true);
                                        
                                        // Style
                                        $objPHPExcel->getActiveSheet()->getStyle("{$startingLetter}{$totalsRow}:{$letter}{$totalsRow}")->applyFromArray($styles['totals']);        

                                        $letter++;
                                        
                                    }
                                    // End of Totals Row
                                    



                                    // Now work out each student's weighted percentage against the maxWeightedScore 
                                    // Not the weighted score of everything they could have achieved, it's against the max
                                    // that has been achieved by any student on the qual
                                    $redoRow = $startStudentRow;
                                    
                                    $studentPassArray = array();
                                    $maxPassTotal = 0;
                                    $passTotal = 0;

                                    foreach($data as $student)
                                    {

                                        $letter = $studentRowCalcCell;
                                        
                                        // ALL
                                        if ($shortCriteriaNames)
                                        {
                                            // This student's weighting
                                            $weighting = $studentWeightings[$student->id];
                                        
                                            // Calculate percentage based on the best
                                            $studentWeightingPercentage = ($maxWeightedScore > 0) ? (float)round( @($weighting / $maxWeightedScore) * 100, 1 ) : 100;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$redoRow}", $studentWeightingPercentage . '%');
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$redoRow}")->applyFromArray( $this->getPercentageStyle($studentWeightingPercentage) );        
                                            $objPHPExcel->getActiveSheet()->getComment("{$letter}{$redoRow}")->getText()->createTextRun($weighting . '/' . $maxWeightedScore);
                                            $letter++;
                                        }
                                        
                                        
                                        // Pass - Best
                                        $studentPass = 0;
                                        $totalPass = 0;
                                        $maxPass = 0;

                                        // First need to work out what constitutes a "Pass" criteria
                                        // By First Letter
                                        if ($method == 'byletter'){
                                                                                        
                                            $firstLetterArray = explode(",", $methodValue);
                                                                                        
                                            if ($firstLetterArray){
                                                foreach($firstLetterArray as $firstLetter){
                                                                                                        
                                                    // Get the field name for this student's count
                                                    $fieldName = 'critawardcnt_'.\gt_make_db_field_safe($firstLetter, $usedFieldNames);
                                                    $fieldNameTtl = 'critcnt_'.\gt_make_db_field_safe($firstLetter, $usedFieldNames);
                                                    
                                                    $met = (int)@$student->$fieldName;
                                                    $critTotal = (int)@$student->$fieldNameTtl;
                                                    
                                                    // Increment this student's met total for this "Pass" criterion letter
                                                    $studentPass += $met;
                                                    
                                                    // Look for the max for this letter as well
                                                    $maxPassLetter = $this->getMaxValue($fieldName, $data);
                                                    $maxPass += (int)$maxPassLetter;
                                                    
                                                    // Increment the total number of Pass criteria of this letter
                                                    $totalPass += $critTotal;
                                                    
                                                }
                                            }
                                                                  
                                            // Pass - Best column
                                            $studentPassPercentageBest = ($maxPass > 0) ? (float)round( @($studentPass / $maxPass) * 100, 1 ) : 100;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$redoRow}", $studentPassPercentageBest . '%');
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$redoRow}")->applyFromArray( $this->getPercentageStyle($studentPassPercentageBest) );        
                                            $objPHPExcel->getActiveSheet()->getComment("{$letter}{$redoRow}")->getText()->createTextRun($studentPass . '/' . $maxPass);
                                            $letter++;
                                            
                                        } 
                                        
                                        elseif ($method == 'all'){
                                            
                                            $studentPass = (int)$student->critawardcnt_all; # Total of all criteria the student has achieved
                                            $totalPass = (int)$student->critcnt_all; # Total of all criteria the student is attached to
                                            $passTotal += $totalPass;
                                            
                                            // Look for the max for this letter as well
                                            $maxPassLetter = $this->getMaxValue('critawardcnt_all', $data);
                                            $maxPass = (int)$maxPassLetter;
                                            
                                            // If no short names, we want to put this in the "Pass Criteria" column for the student as well
                                            if (!$shortCriteriaNames){
                                                $objPHPExcel->getActiveSheet()->setCellValue("{$passCriteriaCellLetter}{$redoRow}", $studentPass);
                                            }
                                            
                                            // Pass - Best column - "Pass" (All in this case) criteria student has achieved / Max "Pass"/all criteria anyone has achieved
                                            $studentPassPercentageBest = ($maxPass > 0) ? (float)round( @($studentPass / $maxPass) * 100, 1 ) : 100;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$redoRow}", $studentPassPercentageBest . '%');
                                            $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$redoRow}")->applyFromArray( $this->getPercentageStyle($studentPassPercentageBest) );        
                                            $objPHPExcel->getActiveSheet()->getComment("{$letter}{$redoRow}")->getText()->createTextRun($studentPass . '/' . $maxPass);
                                            $letter++;
                                            
                                        }
                                        
                                        elseif ($method == 'bygradestructure'){
                                            
                                            $gradeStructureIDs = explode(",", $methodValue);
                                            if ($gradeStructureIDs){
                                                
                                                foreach($gradeStructureIDs as $gradeStructureID){
                                                    
                                                    // Count the total criteria on this qual with this grading structure ID
                                                    if (array_key_exists($gradeStructureID, $gradingStructureCriteriaCountArray)){
                                                        $critTotal = $gradingStructureCriteriaCountArray[$gradeStructureID];
                                                    } else {
                                                        $critTotal = $dataQualification->countCriteriaByGradingStructureID($gradeStructureID);
                                                        $gradingStructureCriteriaCountArray[$gradeStructureID] = $critTotal;
                                                    }
                                                    
                                                    // FUCK MY LIFE, how did it come to this
                                                    $totalPass += $critTotal;
                                                    $passTotal += $critTotal;
                                                    
                                                    // Now count the number of these criteria this student has passed
                                                    $met = $dataQualification->countCriteriaAwardsByGradingStructure($gradeStructureID, $student->id);
                                                    $studentPass += $met;
                                                    
                                                }
                                                
                                                $studentPassArray[$student->id] = $studentPass; # Total number of "Pass" criteria achieved
                                                $objPHPExcel->getActiveSheet()->setCellValue("{$passCriteriaCellLetter}{$redoRow}", $studentPassArray[$student->id]);
                                                                                                
                                                if ($studentPass > $maxPassTotal){
                                                    $maxPassTotal = $studentPass;
                                                }
                                                
                                            }
                                            
                                            $maxPass = $maxPassTotal;
                                            $studentPassBestCell = $letter;
                                            $letter++;
                                            
                                        }
                                        
                                        // Pass - Total column
                                        // Number of pass criteria student has achieved / total number of pass criteria they are on
                                        $studentPassPercentageTotal = ($totalPass > 0) ? (float)round( @($studentPass / $totalPass) * 100, 1 ) : 100;
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$letter}{$redoRow}", $studentPassPercentageTotal . '%');
                                        $objPHPExcel->getActiveSheet()->getStyle("{$letter}{$redoRow}")->applyFromArray( $this->getPercentageStyle($studentPassPercentageTotal) );        
                                        $objPHPExcel->getActiveSheet()->getComment("{$letter}{$redoRow}")->getText()->createTextRun($studentPass . '/' . $totalPass);
                                        $letter++;
                                        
                                        
                                        
                                        $redoRow++;

                                    }
                                    // End looping through students
                                    
                                    
                                    
                                    
                                    
                                    // Now if we were doing by grade structure, need to loop through students again
                                    // As in the previous loop we worked out the best pass score, now we can divide by it
                                    if ($method == 'bygradestructure')
                                    {
                                        
                                        $redoRow = $startStudentRow;
                                        
                                        foreach($data as $student)
                                        {
                                                                          
                                            $studentPassPercentageBest = ($maxPassTotal > 0) ? (float)round( @($studentPassArray[$student->id] / $maxPassTotal) * 100, 1 ) : 100;
                                            $objPHPExcel->getActiveSheet()->setCellValue("{$studentPassBestCell}{$redoRow}", $studentPassPercentageBest . '%');
                                            $objPHPExcel->getActiveSheet()->getStyle("{$studentPassBestCell}{$redoRow}")->applyFromArray( $this->getPercentageStyle($studentPassPercentageBest) );        
                                            $objPHPExcel->getActiveSheet()->getComment("{$studentPassBestCell}{$redoRow}")->getText()->createTextRun($studentPassArray[$student->id] . '/' . $maxPassTotal);
                                            $redoRow++;
                                            
                                        }
                                        
                                    }
                                    
                                    
                                    
                                    // Max calculations
                                    
                                    // Max Pass Criteria
                                    if (!$shortCriteriaNames || $method == 'bygradestructure'){
                                                                         
                                        // Totals row
                                        $avgTotal = ceil( @($passTotal / $cntStudents) );
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$totalPassCriteriaCell}", $avgTotal);  
                                        // Totals row
                                        
                                        // Max
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$passCriteriaCellLetter}{$maxRow}", $maxPass);
                                        
                                    }
                                    
                                    $cellArray = $qualMaxWeightingCell;
                                    
                                    // All - Best
                                    if ($shortCriteriaNames)
                                    {
                                        $cellArray[0]++;
                                        $cell = implode("", $cellArray);
                                        $maxWeightingTotalPercentage = (float)round( @($maxWeightedScore / $totalWeighting) * 100, 1 );
                                        $objPHPExcel->getActiveSheet()->setCellValue("{$cell}", $maxWeightingTotalPercentage . '%');
                                        $objPHPExcel->getActiveSheet()->getStyle("{$cell}")->applyFromArray( $styles['max'] );   
                                        $objPHPExcel->getActiveSheet()->getComment("{$cell}")->getText()->createTextRun($maxWeightedScore . '/' . $totalWeighting);
                                    }    

                                    // Skip Pass - Best column
                                    $cellArray[0]++;
                                    $cell = implode("", $cellArray);
                                    $objPHPExcel->getActiveSheet()->getStyle("{$cell}")->applyFromArray( $styles['max'] );        

                                    // Pass - Total
                                    $cellArray[0]++;
                                    $cell = implode("", $cellArray);
                                    $maxWeightingTotalPercentage = (float)round( @($maxPass / $totalPass) * 100, 1 );
                                    $objPHPExcel->getActiveSheet()->setCellValue("{$cell}", $maxWeightingTotalPercentage . '%');
                                    $objPHPExcel->getActiveSheet()->getStyle("{$cell}")->applyFromArray( $styles['max'] );        
                                    $objPHPExcel->getActiveSheet()->getComment("{$cell}")->getText()->createTextRun($maxPass . '/' . $totalPass);
                                    
                                    
                                }
                                
                                
                                
                            }
                            
                        }
                        
                        
                        
                        // Increment number completed
                        $done++;
                        
                        // Flush out progress
                        $progress = round(($done / $total) * 100, 1);
                        \gt_ajax_progress('pending', array(
                            'progress' => $progress,
                            'time' => time()
                        ));
                        
                        $row++;
                        
                    }
                }
                
                // Autosize columns
                $lastColumn = $objPHPExcel->setActiveSheetIndex( ($sheetIndex - 1) )->getHighestColumn();
                for ($col = 'A'; $col <= $lastColumn; $col++)
                {
                    $objPHPExcel->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
                }
                
            }
        }
        
        
        
        
        
        // End the Spreadsheet generation and save it
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        
        \gt_create_data_directory('reports');
        
        $file = \GT\GradeTracker::dataroot() . '/reports/PassCriteriaProgressReport_' . $User->id . '.xlsx';
        $objWriter->save( $file );
        $download = \gt_create_data_path_code($file);
                
        // Finished
        \gt_ajax_progress(true, array(
            'file' => $download,
            'time' => time()
        ));
        
    }
    
}

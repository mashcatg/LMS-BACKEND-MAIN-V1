<?php
function calculateDueAmount($conn, $student_id, $service_id, $course_id) {
    try {
        // Fetch courses based on the service and course_id
        if ($course_id === null) {
            $stmt = $conn->prepare("SELECT * FROM courses WHERE service_id = :service_id");
            $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        } else {
            $stmt = $conn->prepare("SELECT * FROM courses WHERE service_id = :service_id AND course_id = :course_id");
            $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ensure $courses is an array, return an empty array on error
        if (empty($courses)) {
            return []; // Return empty array instead of error message
        }

        $response = [];
        $totalCourses = count($courses);

        foreach ($courses as $course) {
            // Fetch enrollment for the specific course
            $selectEnrollments = $conn->prepare("SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id AND service_id = :service_id LIMIT 1");
            $selectEnrollments->execute([
                ':student_id' => $student_id, 
                ':course_id' => $course['course_id'], 
                ':service_id' => $service_id
            ]);
            $enrollment = $selectEnrollments->fetch(PDO::FETCH_ASSOC);

            if (!$enrollment) {
                continue; // Skip if no enrollment exists for this course
            }

            // Set dates for enrollment and current day
            $enrollmentDate = new DateTime($enrollment['enrollment_time']);
            $currentDate = new DateTime();

            $courseFee = $enrollment['course_fee'] ?? 0;
            $totalCourseAmount = 0;
            $passedMonths = 0;

            // Count active months only if the course is "monthly"
            if (!empty($course['active_months']) && $course['fee_type'] === 'monthly') {
                $activeMonths = array_map('trim', explode(',', $course['active_months']));

                foreach ($activeMonths as $activeMonth) {
                    $activeDate = DateTime::createFromFormat('F Y', $activeMonth);

                    if ($activeDate && $activeDate >= $enrollmentDate && $activeDate <= $currentDate) {
                        // Count the enrollment month immediately if it’s in the active months
                        if ($activeDate->format('Y-m') === $enrollmentDate->format('Y-m')) {
                            $passedMonths++;
                            $totalCourseAmount += $courseFee;
                        } elseif ($activeDate > $enrollmentDate) {
                            // Count only if the active month is after enrollment month
                            $passedMonths++;
                            $totalCourseAmount += $courseFee;
                        }
                    }
                }
            } else {
                $totalCourseAmount = $courseFee;
            }

            // Calculate total payments and discounts for this course
            $total_payment = $conn->prepare("SELECT SUM(paid_amount) AS total_payment, SUM(discounted_amount) AS total_discount FROM payments WHERE student_id = :student_id AND service_id = :service_id");
            $total_payment->execute([':student_id' => $student_id, ':service_id' => $service_id]);
            $paymentData = $total_payment->fetch(PDO::FETCH_ASSOC);

            $totalPaid = $paymentData['total_payment'] ?? 0;
            $totalDiscount = $paymentData['total_discount'] ?? 0;

            // Calculate the due amount by subtracting total paid and discounts from the total course amount
            $dueAmount = $totalCourseAmount - $totalDiscount - $totalPaid;

            // Add results for this course to the response
            $response[] = [
                'monthly_due' => max(0, $dueAmount),
                'passed_months' => $passedMonths,
                'total_courses' => $totalCourses,
            ];
        }

        return $response; // Return the array with the due amounts

    } catch (Exception $e) {
        return []; // Return an empty array if an exception occurs
    }
}

?>
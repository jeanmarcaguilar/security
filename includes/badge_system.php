<?php
/**
 * Badge Awarding System
 * Handles awarding badges to users based on their assessment performance
 */

require_once '../config.php';

class BadgeSystem {
    private $db;
    private $user_id;
    
    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
    }
    
    /**
     * Award badges based on assessment completion
     */
    public function awardBadgesForAssessment($assessment_data) {
        $awarded_badges = [];
        
        // Get user's assessment history for context
        $history_query = "SELECT * FROM vendor_assessments WHERE assessed_by = :user_id ORDER BY created_at";
        $stmt = $this->db->prepare($history_query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        $all_assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total assessment count
        $total_assessments = count($all_assessments);
        
        // Score-based badges
        $score_badges = $this->checkScoreBadges($assessment_data['score']);
        $awarded_badges = array_merge($awarded_badges, $score_badges);
        
        // Rank-based badges
        $rank_badges = $this->checkRankBadges($assessment_data['rank']);
        $awarded_badges = array_merge($awarded_badges, $rank_badges);
        
        // Category-specific badges
        $category_badges = $this->checkCategoryBadges($assessment_data);
        $awarded_badges = array_merge($awarded_badges, $category_badges);
        
        // Consistency badges (based on total count)
        $consistency_badges = $this->checkConsistencyBadges($total_assessments);
        $awarded_badges = array_merge($awarded_badges, $consistency_badges);
        
        // Milestone badges
        $milestone_badges = $this->checkMilestoneBadges($all_assessments);
        $awarded_badges = array_merge($awarded_badges, $milestone_badges);
        
        // Improvement badges
        if ($total_assessments > 1) {
            $improvement_badges = $this->checkImprovementBadges($all_assessments);
            $awarded_badges = array_merge($awarded_badges, $improvement_badges);
        }
        
        // Award the badges (avoid duplicates)
        $final_awarded = [];
        foreach ($awarded_badges as $badge) {
            if ($this->awardBadgeToUser($badge['id'], $assessment_data['id'])) {
                $final_awarded[] = $badge;
            }
        }
        
        return $final_awarded;
    }
    
    /**
     * Check for score-based badges
     */
    private function checkScoreBadges($score) {
        $badges = [];
        
        if ($score >= 100) {
            $badges[] = $this->getBadgeByRequirement('score', 100);
        } elseif ($score >= 90) {
            $badges[] = $this->getBadgeByRequirement('score', 90);
        } elseif ($score >= 80) {
            $badges[] = $this->getBadgeByRequirement('score', 80);
        } elseif ($score >= 70) {
            $badges[] = $this->getBadgeByRequirement('score', 70);
        }
        
        return array_filter($badges);
    }
    
    /**
     * Check for rank-based badges
     */
    private function checkRankBadges($rank) {
        $badges = [];
        
        $rank_values = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        $rank_value = $rank_values[$rank] ?? 4;
        
        $badge = $this->getBadgeByRequirement('rank', $rank_value);
        if ($badge) {
            $badges[] = $badge;
        }
        
        return array_filter($badges);
    }
    
    /**
     * Check for category-specific badges
     */
    private function checkCategoryBadges($assessment_data) {
        $badges = [];
        $categories = ['password_score', 'phishing_score', 'device_score', 'network_score'];
        
        foreach ($categories as $category) {
            $score = $assessment_data[$category] ?? 0;
            
            if ($score >= 90) {
                // Check for perfect score badge
                if ($score >= 100) {
                    $perfect_badge = $this->getBadgeByRequirement('special', 100);
                    if ($perfect_badge && strpos($perfect_badge['name'], ucfirst(str_replace('_score', '', $category))) !== false) {
                        $badges[] = $perfect_badge;
                    }
                }
                
                // Check for 90+ badge
                $category_badge = $this->getBadgeByRequirement('special', 90);
                if ($category_badge && strpos($category_badge['name'], ucfirst(str_replace('_score', '', $category))) !== false) {
                    $badges[] = $category_badge;
                }
            }
        }
        
        return array_filter($badges);
    }
    
    /**
     * Check for consistency badges
     */
    private function checkConsistencyBadges($total_assessments) {
        $badges = [];
        
        $consistency_milestones = [1, 5, 10, 25, 50];
        
        foreach ($consistency_milestones as $milestone) {
            if ($total_assessments >= $milestone) {
                $badge = $this->getBadgeByRequirement('count', $milestone);
                if ($badge && $badge['category'] === 'consistency') {
                    $badges[] = $badge;
                }
            }
        }
        
        return array_filter($badges);
    }
    
    /**
     * Check for milestone badges (weekly, monthly, etc.)
     */
    private function checkMilestoneBadges($all_assessments) {
        $badges = [];
        
        // Check for weekly milestone (3 assessments in one week)
        $weekly_count = $this->countAssessmentsInPeriod($all_assessments, 7);
        if ($weekly_count >= 3) {
            $badge = $this->getBadgeByRequirement('special', 3);
            if ($badge && $badge['category'] === 'milestone') {
                $badges[] = $badge;
            }
        }
        
        // Check for monthly milestone (10 assessments in one month)
        $monthly_count = $this->countAssessmentsInPeriod($all_assessments, 30);
        if ($monthly_count >= 10) {
            $badge = $this->getBadgeByRequirement('special', 10);
            if ($badge && $badge['category'] === 'milestone') {
                $badges[] = $badge;
            }
        }
        
        return array_filter($badges);
    }
    
    /**
     * Check for improvement badges
     */
    private function checkImprovementBadges($all_assessments) {
        $badges = [];
        
        if (count($all_assessments) < 2) return $badges;
        
        // Get latest and previous assessment
        $latest = $all_assessments[0];
        $previous = $all_assessments[1];
        
        // Check for 20% improvement
        if ($previous['score'] > 0) {
            $improvement = (($latest['score'] - $previous['score']) / $previous['score']) * 100;
            if ($improvement >= 20) {
                $badge = $this->getBadgeByRequirement('special', 20);
                if ($badge && $badge['category'] === 'improvement') {
                    $badges[] = $badge;
                }
            }
        }
        
        // Check for rank improvement from D to B or better
        if ($previous['rank'] === 'D' && in_array($latest['rank'], ['A', 'B'])) {
            $badge = $this->getBadgeByRequirement('special', 1);
            if ($badge && $badge['category'] === 'improvement') {
                $badges[] = $badge;
            }
        }
        
        return array_filter($badges);
    }
    
    /**
     * Get badge by requirement type and value
     */
    private function getBadgeByRequirement($requirement_type, $requirement_value) {
        $query = "SELECT * FROM badges WHERE requirement_type = :req_type AND requirement_value = :req_value AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':req_type', $requirement_type);
        $stmt->bindParam(':req_value', $requirement_value);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Award a specific badge to the user
     */
    private function awardBadgeToUser($badge_id, $assessment_id = null) {
        // Check if user already has this badge
        $check_query = "SELECT COUNT(*) FROM user_achievements WHERE user_id = :user_id AND badge_id = :badge_id";
        $stmt = $this->db->prepare($check_query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':badge_id', $badge_id);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            return false; // Already has this badge
        }
        
        // Get badge points
        $badge_query = "SELECT points FROM badges WHERE id = :badge_id";
        $stmt = $this->db->prepare($badge_query);
        $stmt->bindParam(':badge_id', $badge_id);
        $stmt->execute();
        $badge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Award the badge
        $award_query = "INSERT INTO user_achievements (user_id, badge_id, assessment_id, points_earned) VALUES (:user_id, :badge_id, :assessment_id, :points)";
        $stmt = $this->db->prepare($award_query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':badge_id', $badge_id);
        $stmt->bindParam(':assessment_id', $assessment_id);
        $stmt->bindParam(':points', $badge['points']);
        
        return $stmt->execute();
    }
    
    /**
     * Count assessments in a specific period (days)
     */
    private function countAssessmentsInPeriod($assessments, $days) {
        if (empty($assessments)) return 0;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        $count = 0;
        
        foreach ($assessments as $assessment) {
            if ($assessment['created_at'] >= $cutoff_date) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get all badges for a user
     */
    public function getUserBadges() {
        $query = "SELECT b.*, ua.earned_at, ua.points_earned 
                 FROM user_achievements ua 
                 JOIN badges b ON ua.badge_id = b.id 
                 WHERE ua.user_id = :user_id 
                 ORDER BY ua.earned_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get badge statistics for a user
     */
    public function getUserBadgeStats() {
        $query = "SELECT 
                    COUNT(*) as total_badges,
                    SUM(b.points) as total_points,
                    COUNT(CASE WHEN b.category = 'assessment' THEN 1 END) as assessment_badges,
                    COUNT(CASE WHEN b.category = 'consistency' THEN 1 END) as consistency_badges,
                    COUNT(CASE WHEN b.category = 'improvement' THEN 1 END) as improvement_badges,
                    COUNT(CASE WHEN b.category = 'milestone' THEN 1 END) as milestone_badges,
                    COUNT(CASE WHEN b.category = 'special' THEN 1 END) as special_badges
                  FROM user_achievements ua 
                  JOIN badges b ON ua.badge_id = b.id 
                  WHERE ua.user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Example usage:
/*
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$badge_system = new BadgeSystem($db, $user_id);

// When an assessment is completed:
$assessment_data = [
    'score' => 85,
    'rank' => 'B',
    'password_score' => 90,
    'phishing_score' => 80,
    'device_score' => 85,
    'network_score' => 85,
    'id' => 123 // assessment ID
];

$awarded_badges = $badge_system->awardBadgesForAssessment($assessment_data);

// Get user's badges
$user_badges = $badge_system->getUserBadges();
$user_stats = $badge_system->getUserBadgeStats();
*/
?>

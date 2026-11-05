-- Add scoped branch editors, branch map links, and one-branch assignments for non-admin staff.
-- Back up first. One-time migration; Administrator accounts remain global.
ALTER TABLE branches ADD COLUMN map_link VARCHAR(1000) NULL AFTER finance_email;
INSERT IGNORE INTO permissions(code,module_name,label) VALUES('branches.manage','Branches','Manage active branch details'),('branches.view','Branches','View branch details');
DELETE ub FROM user_branches ub
JOIN (
  SELECT user_id, MIN(branch_id) keep_branch
  FROM user_branches GROUP BY user_id HAVING COUNT(*) > 1
) multi ON multi.user_id=ub.user_id
JOIN users u ON u.id=ub.user_id
JOIN roles r ON r.id=u.role_id
WHERE r.name<>'Administrator' AND ub.branch_id<>multi.keep_branch;

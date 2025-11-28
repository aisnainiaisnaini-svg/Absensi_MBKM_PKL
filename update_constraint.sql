-- Update the CK_GuidancePKL_Status constraint to include 'withdrawn' status
-- This script should be run against the existing database to update the constraint

-- First, drop the existing constraint
ALTER TABLE dbo.Guidance_PKL
DROP CONSTRAINT CK_GuidancePKL_Status;

-- Then recreate the constraint with 'withdrawn' added to the allowed values
ALTER TABLE dbo.Guidance_PKL
ADD CONSTRAINT CK_GuidancePKL_Status 
CHECK ([Status] IN ('pending','diproses','selesai','withdrawn'));
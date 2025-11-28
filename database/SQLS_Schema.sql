CREATE DATABASE db_Pengawas;
GO
USE db_Pengawas;
GO

/* ============================================================
   DROP TABLES
   ============================================================ */
IF OBJECT_ID('dbo.Guidance_PKL', 'U') IS NOT NULL DROP TABLE dbo.Guidance_PKL;
IF OBJECT_ID('dbo.Schedules', 'U') IS NOT NULL DROP TABLE dbo.Schedules;
IF OBJECT_ID('dbo.Activity_Reports', 'U') IS NOT NULL DROP TABLE dbo.Activity_Reports;
IF OBJECT_ID('dbo.Leave_Requests', 'U') IS NOT NULL DROP TABLE dbo.Leave_Requests;
IF OBJECT_ID('dbo.Attendance', 'U') IS NOT NULL DROP TABLE dbo.Attendance;
IF OBJECT_ID('dbo.Participants', 'U') IS NOT NULL DROP TABLE dbo.Participants;
IF OBJECT_ID('dbo.Divisions', 'U') IS NOT NULL DROP TABLE dbo.Divisions;
IF OBJECT_ID('dbo.Users', 'U') IS NOT NULL DROP TABLE dbo.Users;
GO


/* ============================================================
   USERS
   ============================================================ */
CREATE TABLE dbo.Users (
    Id           INT IDENTITY(1,1) PRIMARY KEY,
    Username     VARCHAR(50) NOT NULL UNIQUE,
    [Password]   VARCHAR(255) NOT NULL,
    Email        VARCHAR(100) NOT NULL UNIQUE,
    Full_Name    VARCHAR(100) NOT NULL,
    [Role]       VARCHAR(20) NOT NULL
        CONSTRAINT CK_Users_Role CHECK ([Role] IN ('admin','mahasiswa_mbkm','siswa_pkl')),
    Created_At   DATETIME2 NOT NULL DEFAULT GETDATE(),      -- FIXED WIB
    Updated_At   DATETIME2 NOT NULL DEFAULT GETDATE()       -- FIXED WIB
);
GO


/* ============================================================
   DIVISIONS
   ============================================================ */
CREATE TABLE dbo.Divisions (
    Id            INT IDENTITY(1,1) PRIMARY KEY,
    [Name]        VARCHAR(100) NOT NULL,
    [Description] VARCHAR(MAX) NULL,
    Created_At    DATETIME2 NOT NULL DEFAULT GETDATE()      -- FIXED WIB
);
GO


/* ============================================================
   PARTICIPANTS
   ============================================================ */
CREATE TABLE dbo.Participants (
    Id            INT IDENTITY(1,1) PRIMARY KEY,
    User_Id       INT NOT NULL,
    School        VARCHAR(100) NOT NULL,
    Major         VARCHAR(100) NOT NULL,
    Division_Id   INT NOT NULL,
    Company_Supervisor VARCHAR(100) NULL,
    School_Supervisor  VARCHAR(100) NULL,
    Supervisor_Id INT NULL,
    Start_Date    DATE NOT NULL,
    End_Date      DATE NOT NULL,
    [Status]      VARCHAR(20) NOT NULL
        CONSTRAINT CK_Participants_Status CHECK ([Status] IN ('aktif','selesai','dikeluarkan')),
    Created_At    DATETIME2 NOT NULL DEFAULT GETDATE(),    -- FIXED WIB

    FOREIGN KEY (User_Id) REFERENCES dbo.Users(Id) ON DELETE CASCADE,
    FOREIGN KEY (Division_Id) REFERENCES dbo.Divisions(Id),
    FOREIGN KEY (Supervisor_Id) REFERENCES dbo.Users(Id)
);
GO


/* ============================================================
   ATTENDANCE  (FIXED REALTIME WIB)
   ============================================================ */
CREATE TABLE dbo.Attendance (
    Id              INT IDENTITY(1,1) PRIMARY KEY,
    Participant_Id  INT NOT NULL,
    [Date]          DATE NOT NULL,
    Check_In        DATETIME2 NULL,
    Check_Out       DATETIME2 NULL,
    [Status]        VARCHAR(20) NOT NULL
        CONSTRAINT CK_Attendance_Status CHECK ([Status] IN ('hadir','izin','sakit','alpa')),
    Notes           VARCHAR(MAX) NULL,
    Created_At      DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB

    FOREIGN KEY (Participant_Id) REFERENCES dbo.Participants(Id) ON DELETE CASCADE,
    UNIQUE (Participant_Id, [Date])
);
GO


/* ============================================================
   LEAVE REQUESTS
   ============================================================ */
CREATE TABLE dbo.Leave_Requests (
    Id              INT IDENTITY(1,1) PRIMARY KEY,
    Participant_Id  INT NOT NULL,
    Request_Date    DATE NOT NULL,
    Leave_Type      VARCHAR(30) NOT NULL
        CONSTRAINT CK_LeaveRequests_Type CHECK (Leave_Type IN ('sakit','izin','keperluan_mendesak')),
    Reason          VARCHAR(MAX) NOT NULL,
    Start_Date      DATE NOT NULL,
    End_Date        DATE NOT NULL,
    [Status]        VARCHAR(20) NOT NULL
        CONSTRAINT CK_LeaveRequests_Status CHECK ([Status] IN ('pending','approved','rejected')),
    Approved_By     INT NULL,
    Approved_At     DATETIME2 NULL,
    Notes           VARCHAR(MAX) NULL,
    Created_At      DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB

    FOREIGN KEY (Participant_Id) REFERENCES dbo.Participants(Id) ON DELETE CASCADE,
    FOREIGN KEY (Approved_By) REFERENCES dbo.Users(Id)
);
GO


/* ============================================================
   ACTIVITY REPORTS
   ============================================================ */
CREATE TABLE dbo.Activity_Reports (
    Id                  INT IDENTITY(1,1) PRIMARY KEY,
    Participant_Id      INT NOT NULL,
    Report_Date         DATE NOT NULL,
    Title               VARCHAR(200) NOT NULL,
    [Description]       VARCHAR(MAX) NOT NULL,
    File_Path           VARCHAR(255) NULL,
    Supervisor_Comment  VARCHAR(MAX) NULL,
    Rating              INT NULL,
    Created_At          DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB
    Updated_At          DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB

    FOREIGN KEY (Participant_Id) REFERENCES dbo.Participants(Id) ON DELETE CASCADE
);
GO


/* ============================================================
   SCHEDULES
   ============================================================ */
CREATE TABLE dbo.Schedules (
    Id           INT IDENTITY(1,1) PRIMARY KEY,
    Division_Id  INT NOT NULL,
    Day_Of_Week  VARCHAR(20) NOT NULL
        CONSTRAINT CK_Schedules_DayOfWeek CHECK (Day_Of_Week IN
            ('monday','tuesday','wednesday','thursday','friday','saturday','sunday')),
    Start_Time   TIME NOT NULL,
    End_Time     TIME NOT NULL,
    Created_At   DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB

    FOREIGN KEY (Division_Id) REFERENCES dbo.Divisions(Id) ON DELETE CASCADE
);
GO


/* ============================================================
   GUIDANCE PKL
   ============================================================ */
CREATE TABLE dbo.Guidance_PKL (
    Id              INT IDENTITY(1,1) PRIMARY KEY,
    Participant_Id  INT NOT NULL,
    Title           VARCHAR(200) NOT NULL,
    Question_Text   VARCHAR(MAX) NOT NULL,
    Admin_Response  VARCHAR(MAX) NULL,
    [Status]        VARCHAR(20) NOT NULL
        CONSTRAINT CK_GuidancePKL_Status CHECK ([Status] IN ('pending','diproses','selesai','withdrawn')),
    Created_At      DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB
    Responded_At    DATETIME2 NULL,

    FOREIGN KEY (Participant_Id) REFERENCES dbo.Participants(Id) ON DELETE CASCADE
);
GO


/* ============================================================
   SETTINGS
   ============================================================ */
DROP TABLE IF EXISTS dbo.Settings;
GO

CREATE TABLE dbo.Settings (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Setting_Key VARCHAR(100) NOT NULL UNIQUE,
    Setting_Value VARCHAR(100) NOT NULL
);
GO

INSERT INTO dbo.Settings (Setting_Key, Setting_Value)
VALUES ('active_month', FORMAT(GETDATE(), 'yyyy-MM'));
GO


/* ============================================================
   DEFAULT USERS
   ============================================================ */
INSERT INTO dbo.Users (Username, [Password], Email, Full_Name, [Role])
VALUES
('admin',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@company.com', 'Administrator', 'admin'),
('mahasiswa',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa1@company.com', 'Mahasiswa MBKM', 'mahasiswa_mbkm'),
('siswa',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siswapkl1@company.com', 'Siswa PKL', 'siswa_pkl');
GO


/* ============================================================
   DEFAULT DIVISIONS
   ============================================================ */
INSERT INTO dbo.Divisions ([Name], [Description])
VALUES
('IT Development', 'Divisi pengembangan aplikasi'),
('Marketing', 'Divisi pemasaran'),
('HR', 'Divisi SDM'),
('Finance', 'Divisi keuangan');
GO


/* ============================================================
   SQL LOGIN AIS
   ============================================================ */
CREATE LOGIN Ais WITH PASSWORD = '123', CHECK_POLICY = OFF;

USE db_Pengawas;
CREATE USER Ais FOR LOGIN Ais;

ALTER ROLE db_datareader ADD MEMBER Ais;
ALTER ROLE db_datawriter ADD MEMBER Ais;
ALTER ROLE db_ddladmin ADD MEMBER Ais;
GO

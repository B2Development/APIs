<?php
/**
 * Class to hold constants
 * User: Andrew Strasburger
 * Date: 8/19/14
 */

// in progress
class Constants {

    public function _construct()
    {
    }


    //-----Application Constants-----

    // Application Type Names
    // Defined by the Core - taken from: psql -Upostgres bpdb -c "select * from bp.application_lookup order by app_id"
    const APPLICATION_TYPE_NAME_FILE_LEVEL          = 'file-level';
    const APPLICATION_TYPE_NAME_EXCHANGE            = 'Exchange';
    const APPLICATION_TYPE_NAME_SQL_SERVER          = 'SQL Server';
    const APPLICATION_TYPE_NAME_ARCHIVE             = 'Archive';
    const APPLICATION_TYPE_NAME_VMWARE              = 'VMware';
    const APPLICATION_TYPE_NAME_HYPER_V             = 'Hyper-V';
    const APPLICATION_TYPE_NAME_SYSTEM_METADATA     = 'System Metadata';
    const APPLICATION_TYPE_NAME_ORACLE              = 'Oracle';
    const APPLICATION_TYPE_NAME_SHAREPOINT          = 'SharePoint';
    const APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE = 'UCS Service Profile'; //Cisco UCS
    const APPLICATION_TYPE_NAME_NDMP_DEVICE         = 'NDMP Device';  //NDMP device needed for job display
    const APPLICATION_TYPE_NAME_XEN                 = 'Xen';
    const APPLICATION_TYPE_NAME_AHV                 = 'AHV';
    const APPLICATION_TYPE_NAME_BLOCK_LEVEL         = 'image-level';

    // Application IDs (app_id)
    // Defined by the Core - taken from: psql -Upostgres bpdb -c "select * from bp.application_lookup order by app_id"
    const APPLICATION_ID_FILE_LEVEL             = 1;
    const APPLICATION_ID_EXCHANGE_2003          = 2;
    const APPLICATION_ID_EXCHANGE_2007          = 3;
    const APPLICATION_ID_EXCHANGE_2010          = 4;
    const APPLICATION_ID_EXCHANGE_2013          = 5;
    const APPLICATION_ID_EXCHANGE_2016          = 6;
    const APPLICATION_ID_SQL_SERVER_2005        = 21;
    const APPLICATION_ID_SQL_SERVER_2008        = 22;
    const APPLICATION_ID_SQL_SERVER_2008_R2     = 23;
    const APPLICATION_ID_SQL_SERVER_2012        = 24;
    const APPLICATION_ID_SQL_SERVER_2014        = 25;
    const APPLICATION_ID_SQL_SERVER_2016        = 26;
    const APPLICATION_ID_SQL_SERVER_2017        = 27;
    const APPLICATION_ID_ARCHIVE                = 30;
    const APPLICATION_ID_VMWARE                 = 40;
    const APPLICATION_ID_HYPER_V_2008_R2        = 50;
    const APPLICATION_ID_HYPER_V_2012           = 51;
    const APPLICATION_ID_HYPER_V_2016           = 52;
    const APPLICATION_ID_SYSTEM_METADATA        = 60;
    const APPLICATION_ID_ORACLE_11              = 100;  // Not Applicable in our Environment
    const APPLICATION_ID_ORACLE_12              = 101;
    const APPLICATION_ID_ORACLE_10              = 102;
    const APPLICATION_ID_SHAREPOINT_2007        = 110;
    const APPLICATION_ID_SHAREPOINT_2010        = 111;
    const APPLICATION_ID_SHAREPOINT_2013        = 112;
    const APPLICATION_ID_SHAREPOINT_2016        = 113;
    const APPLICATION_ID_UCS_SERVICE_PROFILE    = 120; //Cisco UCS Service Profile
    const APPLICATION_ID_VOLUME                 = 130;  //NDMP
    const APPLICATION_ID_XEN                    = 140;
    const APPLICATION_ID_AHV                    = 141;
    const APPLICATION_ID_BLOCK_LEVEL            = 150;

    // Application Names
    // Defined by the Core - taken from: psql -Upostgres bpdb -c "select * from bp.application_lookup order by app_id"
    const APPLICATION_NAME_FILE_LEVEL           = 'file-level';
    const APPLICATION_NAME_EXCHANGE_2003        = 'Exchange 2003';
    const APPLICATION_NAME_EXCHANGE_2007        = 'Exchange 2007';
    const APPLICATION_NAME_EXCHANGE_2010        = 'Exchange 2010';
    const APPLICATION_NAME_EXCHANGE_2013        = 'Exchange 2013';
    const APPLICATION_NAME_EXCHANGE_2016        = 'Exchange 2016';
    const APPLICATION_NAME_SQL_SERVER_2005      = 'SQL Server 2005';
    const APPLICATION_NAME_SQL_SERVER_2008      = 'SQL Server 2008';
    const APPLICATION_NAME_SQL_SERVER_2008_R2   = 'SQL Server 2008 R2';
    const APPLICATION_NAME_SQL_SERVER_2012      = 'SQL Server 2012';
    const APPLICATION_NAME_SQL_SERVER_2014      = 'SQL Server 2014';
    const APPLICATION_NAME_SQL_SERVER_2016      = 'SQL Server 2016';
    const APPLICATION_NAME_SQL_SERVER_2017      = 'SQL Server 2017';
    const APPLICATION_NAME_ARCHIVE              = 'Archive';
    const APPLICATION_NAME_VMWARE               = 'VMware';
    const APPLICATION_NAME_HYPER_V_2008_R2      = 'Hyper-V 2008 R2';
    const APPLICATION_NAME_HYPER_V_2012         = 'Hyper-V 2012';
    const APPLICATION_NAME_HYPER_V_2016         = 'Hyper-V 2016';
    const APPLICATION_NAME_SYSTEM_METADATA      = 'System Metadata';
    const APPLICATION_NAME_ORACLE_10            = 'Oracle 10';
    const APPLICATION_NAME_ORACLE_11            = 'Oracle 11';  // Not Applicable in our Environment
    const APPLICATION_NAME_ORACLE_12            = 'Oracle 12';
    const APPLICATION_NAME_SHAREPOINT_2007      = 'SharePoint 2007';
    const APPLICATION_NAME_SHAREPOINT_2010      = 'SharePoint 2010';
    const APPLICATION_NAME_SHAREPOINT_2013      = 'SharePoint 2013';
    const APPLICATION_NAME_SHAREPOINT_2016      = 'SharePoint 2016';
    const APPLICATION_NAME_UCS_SERVICE_PROFILE  = 'UCS Service Profile'; //Cisco UCS
    const APPLICATION_NAME_VOLUME               = 'Volume';  //NDMP
    const APPLICATION_NAME_XEN                  = 'Xen';

    //-----Archive Constants-----

    // Archive Statuses
    const ARCHIVE_STATUS_ARCHIVE_FAILED =       'archive failed';
    const ARCHIVE_STATUS_ARCHIVE_IN_PROGRESS =  'archive in progress';
    const ARCHIVE_STATUS_ARCHIVE_SUCCESS =      'archive success';
    const ARCHIVE_STATUS_IMPORT_FAILED =        'import failed';
    const ARCHIVE_STATUS_IMPORT_IN_PROGRESS =   'import in progress';
    const ARCHIVE_STATUS_IMPORT_SUCCESS =       'import success';

    //-----Backup Constants-----

    // Application Type Display names.
    const BACKUP_METHOD_IMAGE_LEVEL                 = 'image-level';
    const APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL    = 'Image Level';
    const APPLICATION_TYPE_DISPLAY_NAME_FILE_LEVEL     = 'File Level';

    // Regular Backup Types
    const BACKUP_TYPE_MASTER                    = 'master';
    const BACKUP_TYPE_DIFFERENTIAL              = 'differential';
    const BACKUP_TYPE_INCREMENTAL               = 'incremental';
    const BACKUP_TYPE_BAREMETAL                 = 'baremetal';
    const BACKUP_TYPE_SELECTIVE                 = 'selective';
    const BACKUP_TYPE_BLOCK_FULL                = 'image full';
    const BACKUP_TYPE_BLOCK_INCREMENTAL         = 'image incremental';
    const BACKUP_TYPE_BLOCK_DIFFERENTIAL        = 'image differential';
    const BACKUP_TYPE_MSSQL_FULL                = 'mssql full';
    const BACKUP_TYPE_MSSQL_DIFFERENTIAL        = 'mssql differential';
    const BACKUP_TYPE_MSSQL_TRANSACTION         = 'mssql transaction';
    const BACKUP_TYPE_MSSQL_FULL_ALT            = 'sql full';
    const BACKUP_TYPE_MSSQL_DIFFERENTIAL_ALT    = 'sql differential';
    const BACKUP_TYPE_MSSQL_TRANSACTION_ALT     = 'sql transaction';
    const BACKUP_TYPE_EXCHANGE_FULL             = 'exchange full';
    const BACKUP_TYPE_EXCHANGE_DIFFERENTIAL     = 'exchange differential';
    const BACKUP_TYPE_EXCHANGE_INCREMENTAL      = 'exchange incremental';
    const BACKUP_TYPE_LEGACY_MSSQL_FULL         = 'legacy mssql full';
    const BACKUP_TYPE_LEGACY_MSSQL_DIFF         = 'legacy mssql diff';
    const BACKUP_TYPE_LEGACY_MSSQL_TRANS        = 'legacy mssql trans';
    const BACKUP_TYPE_VMWARE_FULL               = 'vmware full';
    const BACKUP_TYPE_VMWARE_DIFFERENTIAL       = 'vmware differential';
    const BACKUP_TYPE_VMWARE_INCREMENTAL        = 'vmware incremental';
    const BACKUP_TYPE_HYPER_V_FULL              = 'hyperv full';
    const BACKUP_TYPE_HYPER_V_INCREMENTAL       = 'hyperv incremental';
    const BACKUP_TYPE_HYPER_V_DIFFERENTIAL      = 'hyperv differential';
    const BACKUP_TYPE_HYPER_V_FULL_ALT          = 'hyper-v full';
    const BACKUP_TYPE_HYPER_V_INCREMENTAL_ALT   = 'hyper-v incremental';
    const BACKUP_TYPE_HYPER_V_DIFFERENTIAL_ALT  = 'hyper-v differential';
    const BACKUP_TYPE_ORACLE_FULL               = 'oracle full';
    const BACKUP_TYPE_ORACLE_INCR               = 'oracle incr';
    const BACKUP_TYPE_ORACLE_INCR_ALT           = 'oracle incremental';
    const BACKUP_TYPE_SHAREPOINT_FULL           = 'sharepoint full';
    const BACKUP_TYPE_SHAREPOINT_DIFF           = 'sharepoint diff';
    const BACKUP_TYPE_SHAREPOINT_DIFFERENTIAL   = 'sharepoint differential';
    const BACKUP_TYPE_SYSTEM_METADATA           = 'system metadata';
    const BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL  = 'ucs service profile full';
    const BACKUP_TYPE_NDMP_FULL                 = 'ndmp full';
    const BACKUP_TYPE_NDMP_DIFF                 = 'ndmp diff';
    const BACKUP_TYPE_NDMP_INCR                 = 'ndmp incr';
    const BACKUP_TYPE_NDMP_DIFF_ALT             = 'ndmp differential';
    const BACKUP_TYPE_NDMP_INCR_ALT             = 'ndmp incremental';
    const BACKUP_TYPE_XEN_FULL                  = 'xen full';
    const BACKUP_TYPE_AHV_FULL                  = 'ahv full';
    const BACKUP_TYPE_AHV_DIFF                  = 'ahv diff';
    const BACKUP_TYPE_AHV_INCR                  = 'ahv incr';
    const BACKUP_TYPE_AHV_INCR_ALT              = 'ahv incremental';

    // Schedule Backup Types (to go on a calendar, the backup type must be capitalized
    const BACKUP_SCHEDULE_TYPE_BLOCK_FULL                = 'Image Full';
    const BACKUP_SCHEDULE_TYPE_BLOCK_INCREMENTAL         = 'Image Incremental';


    // Extra Backup Types
    const BACKUP_TYPE_RESTORE                   = 'restore';
    const BACKUP_TYPE_BLOCK_RESTORE             = 'image restore';
    const BACKUP_TYPE_VIRTUAL_RESTORE           = 'virtual restore';
    const BACKUP_TYPE_REPLICA_RESTORE           = 'replica restore';
    const BACKUP_TYPE_INTEGRATED_BM_RESTORE     = 'integrated bm restore';
    const BACKUP_TYPE_VERIFY                    = 'verify';
    const BACKUP_COPY_ARCHIVE_RESTORE           = 'archive restore';
    const BACKUP_COPY_DISPLAY_ARCHIVE_RESTORE   = "Import";
    const BACKUP_COPY_ARCHIVE                   = 'archive';
    const BACKUP_COPY_DISPLAY_ARCHIVE           = "Backup Copy";

    // Securesync Backup Types (Replication and Vaulting)
    const BACKUP_TYPE_SECURESYNC_MASTER                    = 'securesync master';
    const BACKUP_TYPE_SECURESYNC_DIFFERENTIAL              = 'securesync differential';
    const BACKUP_TYPE_SECURESYNC_INCREMENTAL               = 'securesync incremental';
    const BACKUP_TYPE_SECURESYNC_BAREMETAL                 = 'securesync baremetal';
    const BACKUP_TYPE_SECURESYNC_BLOCK_FULL                = 'securesync image full';
    const BACKUP_TYPE_SECURESYNC_BLOCK_INCREMENTAL         = 'securesync image incr';
    const BACKUP_TYPE_SECURESYNC_BLOCK_DIFFERENTIAL        = 'securesync image diff';
    const BACKUP_TYPE_SECURESYNC_DPU_STATE                 = 'securesync dpustate';
    const BACKUP_TYPE_SECURESYNC_LOCAL_DIRECTORY           = 'securesync localdir';
    const BACKUP_TYPE_SECURESYNC_MS_SQL                    = 'securesync SQL';   // (legacy securesync: includes full, differential, transaction logs)
    const BACKUP_TYPE_SECURESYNC_EXCHANGE                  = 'securesync exchange';  //Securesync Exchange Server backup (legacy - no type available)
    const BACKUP_TYPE_SECURESYNC_MSSQL_FULL                = 'securesync mssql full';
    const BACKUP_TYPE_SECURESYNC_MSSQL_DIFFERENTIAL        = 'securesync mssql diff';
    const BACKUP_TYPE_SECURESYNC_MSSQL_TRANSACTION         = 'securesync mssql trans';
    const BACKUP_TYPE_SECURESYNC_EXCHANGE_FULL             = 'securesync msexch full';
    const BACKUP_TYPE_SECURESYNC_EXCHANGE_DIFFERENTIAL     = 'securesync msexch diff';
    const BACKUP_TYPE_SECURESYNC_EXCHANGE_INCREMENTAL      = 'securesync msexch incr';
    const BACKUP_TYPE_SECURESYNC_VMWARE_FULL               = 'securesync vmware full';
    const BACKUP_TYPE_SECURESYNC_VMWARE_DIFFERENTIAL       = 'securesync vmware diff';
    const BACKUP_TYPE_SECURESYNC_VMWARE_INCREMENTAL        = 'securesync vmware incr';
    const BACKUP_TYPE_SECURESYNC_HYPER_V_FULL              = 'securesync hyperv full';
    const BACKUP_TYPE_SECURESYNC_HYPER_V_INCREMENTAL       = 'securesync hyperv incr';
    const BACKUP_TYPE_SECURESYNC_HYPER_V_DIFFERENTIAL      = 'securesync hyperv diff';
    const BACKUP_TYPE_SECURESYNC_ORACLE_FULL               = 'securesync oracle full';
    const BACKUP_TYPE_SECURESYNC_ORACLE_INCR               = 'securesync oracle incr';
    const BACKUP_TYPE_SECURESYNC_SHAREPOINT_FULL           = 'securesync sharepoint full';
    const BACKUP_TYPE_SECURESYNC_SHAREPOINT_DIFF           = 'securesync sharepoint diff';
    const BACKUP_TYPE_SECURESYNC_SYSTEM_METADATA           = 'securesync system metadata';
    const BACKUP_TYPE_SECURESYNC_UCS_SERVICE_PROFILE_FULL  = 'securesync ucs service profile full';
    const BACKUP_TYPE_SECURESYNC_NDMP_FULL                 = 'securesync ndmp full';
    const BACKUP_TYPE_SECURESYNC_NDMP_DIFF                 = 'securesync ndmp diff';
    const BACKUP_TYPE_SECURESYNC_NDMP_INCR                 = 'securesync ndmp incr';
    const BACKUP_TYPE_SECURESYNC_XEN_FULL                  = 'securesync xen full';
    const BACKUP_TYPE_SECURESYNC_AHV_FULL                  = 'securesync ahv full';
    const BACKUP_TYPE_SECURESYNC_AHV_DIFF                  = 'securesync ahv diff';
    const BACKUP_TYPE_SECURESYNC_AHV_INCR                  = 'securesync ahv incr';

    //returns from bp_get_active_repliction_job_info and
    // bp_get_replication_job_history_info
    const BACKUP_TYPE_SATORI_REPLICATION_VMWARE_FULL = 'VMwareFull';
    const BACKUP_TYPE_SATORI_REPLICATION_VMWARE_INCREMENTAL = 'VMwareIncr';
    const BACKUP_TYPE_SATORI_REPLICATION_VMWARE_DIFFERENTIAL = 'VMwareDiff';
    const BACKUP_TYPE_SATORI_REPLICATION_HV_FULL = 'HypervFull';
    const BACKUP_TYPE_SATORI_REPLICATION_HV_INCREMENTAL = 'HypervIncr';
    const BACKUP_TYPE_SATORI_REPLICATION_HV_DIFFERENTIAL = 'HypervDiff';


    const BACKUP_TYPE_SATORI_REPLICATION_SQL_FULL = 'SQLFull';
    const BACKUP_TYPE_SATORI_REPLICATION_SQL_DIFFERENTIAL = 'SQLDiff';
    const BACKUP_TYPE_SATORI_REPLICATION_SQL_INCREMENTAL = 'SQLIncr';
    const BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_FULL = 'ExchFull';
    const BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_DIFFERENTIAL = 'ExchDiff';
    const BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_INCREMENTAL = 'ExchIncr';

    // Backup Display Types
    // Reports::get_backup_type_display_name will map backup types to a backup display type
    const BACKUP_DISPLAY_TYPE_FULL              = 'Full';
    const BACKUP_DISPLAY_TYPE_DIFFERENTIAL      = 'Differential';
    const BACKUP_DISPLAY_TYPE_INCREMENTAL       = 'Incremental';
    const BACKUP_DISPLAY_TYPE_BAREMETAL         = 'Bare Metal';
    const BACKUP_DISPLAY_TYPE_SELECTIVE         = 'Selective';
    const BACKUP_DISPLAY_TYPE_TRANSACTION       = 'Transaction';
    const BACKUP_DISPLAY_TYPE_RESTORE           = 'Restore';
    const BACKUP_DISPLAY_TYPE_WIR               = 'Windows Replica';
    const BACKUP_DISPLAY_TYPE_REPLICA_RESTORE   = 'VM Replica';

    //Backup Strategy Display Strings
    const BACKUP_STRATEGY_FULL_STRING       = 'Fulls';
    const BACKUP_STRATEGY_FULL_INCR_STRING  = 'Fulls and Incrementals';
    const BACKUP_STRATEGY_FULL_DIFF_STRING  = 'Fulls and Differentials';
    const BACKUP_STRATEGY_INCR_4EVER_STRING = 'Incremental Forever';
    const BACKUP_STRATEGY_FULL_TRANS_STRING = 'Fulls and Transaction Logs';

    // Backup Statuses (status)
    const BACKUP_STATUS_SUCCESS     = 0;
    const BACKUP_STATUS_WARNINGS    = 1;
    const BACKUP_STATUS_FAILURE     = 2;

    //-----Backup Copy Constants-----

    // Regular Backup Types
    const BACKUP_COPY_TARGET_TYPE_APPLIANCE         = 'appliance';
    const BACKUP_COPY_TARGET_TYPE_UNITRENDS_CLOUD   = 'Unitrends_cloud';

    //-----Client Constants-----

    // Add Client Display Name
    const ADD_CLIENT_DISPLAY_NAME_VMWARE            = 'VMware';
    const ADD_CLIENT_DISPLAY_NAME_HYPER_V           = 'Hyper-V';
    const ADD_CLIENT_DISPLAY_NAME_WINDOWS           = 'Windows';
    const ADD_CLIENT_DISPLAY_NAME_CISCO_UCS_MANAGER = 'Cisco UCS Manager';
    //const ADD_CLIENT_DISPLAY_NAME_CISCO_UCS_CENTRAL   = 'Cisco UCS Central';  //UCS Central is not currently supported
    const ADD_CLIENT_DISPLAY_NAME_NAS_NDMP_CLIENT   = 'NAS NDMP Client';
    const ADD_CLIENT_DISPLAY_NAME_XEN               = 'Xen';
    const ADD_CLIENT_DISPLAY_NAME_AHV               = 'AHV Host';
    const ADD_CLIENT_DISPLAY_NAME_ALL_OTHER_OS      = 'All other OS';

    // App Aware (VSS) Flag (Block Agent) app_aware_flg
    const APP_AWARE_FLG_NOT_AWARE_OF_APPLICATIONS_VSS_FULL  = 0;  // Image Level Backups Quiesce; Exchange Backups Cannot Be Run
    const APP_AWARE_FLG_AWARE_OF_APPLICATIONS_VSS_COPY      = 1;  // Exchange Backups Quiesce; Image Level Backups Do Not Quiesce

    // Asset Type
    const ASSET_TYPE_ESX            = 'esx';
    const ASSET_TYPE_HYPER_V_VM     = 'Hyper-V:VM';
    const ASSET_TYPE_PHYSICAL       = 'physical';
    const ASSET_TYPE_V_CENTER       = 'vCenter';
    const ASSET_TYPE_VMWARE_VM      = 'VMware:VM';
    const ASSET_TYPE_VMWARE_HOST    = 'VMware Host';
    const ASSET_TYPE_XEN_VM         = 'Xen:VM';
    const ASSET_TYPE_AHV_VM         = 'AHV:VM';

    // Client Names vCenter-RRC
    const CLIENT_NAME_VCENTER_RRC   = "vCenter-RRC";

    //Client Name Ending for NAS source
    const NAS_POSTFIX               = "-NAS-RRC";

    // Block Supported Constants
    const BLK_SUPPORT_BLOCK_NOT_SUPPORTED                           = 0;  // Does not support block backups
    const BLK_SUPPORT_DRIVER_VERSION_IS_OLD                         = 1;  // Supports block fulls, but not incrementals
    const BLK_SUPPORT_CBT_DRIVER_NOT_INSTALLED                      = 2;  // Supports block fulls, but not incrementals
    const BLK_SUPPORT_DRIVER_IS_INSTALLED_BUT_REBOOT_IS_REQUIRED    = 3;  // Supports block fulls, but not incrementals
    const BLK_SUPPORT_CBT_DRIVER_INSTALLED_AND_REBOOTED             = 4;  // Supports block fulls and incrementals

    // Generic Property
    const GENERIC_PROPERTY_CISCO_UCS_MANAGER    = 0;
    const GENERIC_PROPERTY_CISCO_UCS_CENTRAL    = 1;
    const GENERIC_PROPERTY_NDMP_DEVICE          = 4;
    const GENERIC_PROPERTY_XEN                  = 5;
    const GENERIC_PROPERTY_AHV                  = 6;

    // Operating System Type (os type)
    const OS_DOS            = 1;
    const OS_WIN_16         = 2;
    const OS_NT             = 3;
    const OS_WINDOWS_95     = 4;
    const OS_OS_2           = 5;
    const OS_NOVELL         = 6;
    const OS_UNIX3          = 7;
    const OS_ODT            = 8;
    const OS_OS5            = 9;
    const OS_UNIX4          = 10;
    const OS_SOLARIS        = 11;
    const OS_SUN_OS         = 12;
    const OS_NOVELL_4       = 13;
    const OS_OSF1           = 14;
    const OS_NT_SERVER      = 15;
    const OS_AIX            = 16;  // Not Applicable in our Environment
    const OS_SGI            = 17;
    const OS_DGUX           = 18;
    const OS_HP_UX          = 19;
    const OS_LINUX          = 20;
    const OS_BSDI           = 21;
    const OS_USL            = 22;
    const OS_UNIXWARE       = 23;
    const OS_FREEBSD        = 24;
    const OS_MACOS          = 25;
    const OS_NOVELL_5       = 26;  // The API Guide lists this as just Novell, but there is already one Novell, so I am guessing this is Novell 5
    const OS_Novell_6       = 27;
    const OS_NOVELL_65      = 28;
    const OS_WINDOWS_2000   = 29;
    const OS_WINDOWS_XP     = 30;
    const OS_WINDOWS_2003   = 31;
    const OS_WINDOWS_VISTA  = 32;
    const OS_IBMI5          = 33;
    const OS_OES            = 34;
    const OS_WINDOWS_2008   = 35;
    const OS_WIN_2008_R2    = 36;
    const OS_WINDOWS_7      = 37;
    const OS_WINDOWS_8      = 38;
    const OS_WINDOWS_2012   = 39;
    const OS_GENERIC        = 40;
    const OS_WINDOWS_8_1    = 41;
    const OS_WINDOWS_2012_R2= 42;
    const OS_WINDOWS_10     = 43;
    const OS_WINDOWS_2016   = 44;

    // Operating System Family (os_family) (core logic below)
    const OS_FAMILY_DOS             = 'DOS';
    const OS_FAMILY_OES             = 'Novell OES';
    const OS_FAMILY_OS2             = 'OS/2';
    const OS_FAMILY_UNIX            = 'Unix';
    const OS_FAMILY_SCO             = 'SCO';
    const OS_FAMILY_SOLARIS         = 'Solaris';
    const OS_FAMILY_AIX             = 'AIX';
    const OS_FAMILY_SGI             = 'SGI Irix';
    const OS_FAMILY_HPUX            = 'HP-UX';
    const OS_FAMILY_FREE_BSD        = 'FreeBSD';
    const OS_FAMILY_MAC_OS          = 'Mac OS';
    const OS_FAMILY_LINUX           = 'Linux';
    const OS_FAMILY_I_SERIES        = 'iSeries';
    const OS_FAMILY_NOVELL_NETWARE  = 'Novell Netware';
    const OS_FAMILY_WINDOWS         = 'Windows';
    const OS_FAMILY_OTHER           = 'Other OS';
    const OS_FAMILY_GENERIC         = 'Generic OS';  // "NDMP NAS" or "Cisco UCS Manager"
    /*  Core logic for OS Family
     int getClientOSFamily(int os, char **string)
{
    switch (os) {
        case NT_DOS:
            *string = strdup("DOS");
            break;
        case NT_OES:
            *string = strdup("Novell OES");
            break;
        case NT_OS2:
            *string = strdup("OS/2");
            break;
        case NT_UNIX3:
        case NT_UNIX4:
        case NT_SUNOS:
        case NT_OSF1:
        case NT_DGUX:
        case NT_BSDI:
        case NT_USL:
        case NT_UW:
            *string = strdup("Unix");
            break;
        case NT_OS5:
        case NT_ODT:
            *string = strdup("SCO");
            break;
        case NT_SOLARIS:
            *string = strdup("Solaris");
            break;
        case NT_AIX:
            *string = strdup("AIX");
            break;
        case NT_SGI:
            *string = strdup("SGI Irix");
            break;
        case NT_HP1:
            *string = strdup("HP-UX");
            break;
        case NT_FBSD:
            *string = strdup("FreeBSD");
            break;
        case NT_MACOS:
            *string = strdup("Mac OS");
            break;
        case NT_LINUX:
            *string = strdup("Linux");
            break;
        case NT_IBMI5:
            *string = strdup("iSeries");
            break;

        case NT_NOV3:
        case NT_NOV4:
        case NT_NOV5:
        case NT_NOV6:
        case NT_NOV65:
            *string = strdup("Novell Netware");
            break;
        case NT_WIN16:
        case NT_NT:
        case NT_W95:
        case NT_2K:
        case NT_NTS:
        case NT_XP:
        case NT_2K3:
        case NT_VISTA:
        case NT_WIN2K8:
        case NT_WIN2K8R2:
        case NT_WIN7:
        case NT_WIN8:
        case NT_WIN2K12:
        case NT_WIN81:
        case NT_WIN2K12R2:
        case NT_WIN10:
            *string = strdup("Windows");
            break;
        default:
            *string = strdup("Other OS");
            break;

    }

    if (*string != NULL)
        return SUCCESS;
    else
        return FAILED;
}
    */

    //-----Error Code Constants-----

    // Error Code

    const ERROR_CODE_REPLICATION_FAILED_DUE_TO_INSECURE_CONNECTION  = 12505;
    const ERROR_CODE_CROSS_REPLICATION_FAILED_DUE_TO_A_LACK_CREDENTIALS  = 12506;

    //-----Inventory Constants-----

    // Hypervisor Type (hv type)
    const HV_UNKNOWN    = 0;
    const HV_WINDOWS    = 1;
    const HV_HYPER_V    = 2;
    const HV_VMWARE     = 3;

    // Inventory ID - by type

    // Inventory ID - System Nodes
    const INVENTORY_ID_SYSTEM = 1001;
    const INVENTORY_ID_NAVGROUP = 1002;

    // Inventory ID - Client Nodes
    const INVENTORY_ID_CLIENT_AIX       = 2001;
    const INVENTORY_ID_CLIENT_DOS       = 2002;
    const INVENTORY_ID_CLIENT_FREE_BSD  = 2003;
    const INVENTORY_ID_CLIENT_HPUX      = 2004;
    const INVENTORY_ID_CLIENT_I_SERIES  = 2005;
    const INVENTORY_ID_CLIENT_LINUX     = 2006;
    const INVENTORY_ID_CLIENT_MAC       = 2007;
    const INVENTORY_ID_CLIENT_NETWARE   = 2008;
    const INVENTORY_ID_CLIENT_OES       = 2009;
    const INVENTORY_ID_CLIENT_OS2       = 2010;
    const INVENTORY_ID_CLIENT_SGI       = 2011;
    const INVENTORY_ID_CLIENT_SCO       = 2012;
    const INVENTORY_ID_CLIENT_SOLARIS   = 2013;
    const INVENTORY_ID_CLIENT_UNIX      = 2014;
    const INVENTORY_ID_CLIENT_WINDOWS   = 2015;
    const INVENTORY_ID_CLIENT_OTHER     = 2016;

    // Inventory ID - NAS Nodes
    const INVENTORY_ID_NAS_CLIENT   = 3001;

    // Inventory ID - Cisco UCS Nodes
    const INVENTORY_ID_CISCO_UCS_CLIENT             = 101001;
    const INVENTORY_ID_CISCO_UCS_SERVICE_PROFILE    = 101002;

    // Inventory ID - NDMP Nodes
    const INVENTORY_ID_NDMP_ClIENT         = 103001;
    const INVENTORY_ID_NDMP_VOLUME         = 103002;

    // Inventory ID - Exchange Nodes
    const INVENTORY_ID_EXCHANGE_APPLICATION     = 102001;
    const INVENTORY_ID_EXCHANGE_DATABASE        = 102002;
    //const INVENTORY_ID_EXCHANGE_STORAGE_GROUP   = 102003; // Not needed at the moment

    // Inventory ID - Hyper-V Nodes
    const INVENTORY_ID_HYPER_V_CLUSTER              = 201001;
    const INVENTORY_ID_HYPER_V_SERVER               = 201002;  //Application Node
    const INVENTORY_ID_HYPER_V_VM                   = 201003;
    const INVENTORY_ID_HYPER_V_CLUSTERED_VM         = 201004;
    const INVENTORY_ID_HYPER_V_ADMINISTRATIVE_VM    = 201005;
    const INVENTORY_ID_HYPER_V_VM_DISK              = 201006;
    const INVENTORY_ID_HYPER_V_WIR_VM               = 201007;  //Hyper-V Windows Instant Recovery VM

    // Inventory ID - Oracle Nodes
    const INVENTORY_ID_ORACLE_APPLICATION   = 104001;
    const INVENTORY_ID_ORACLE_DATABASE      = 104002;

    // Inventory ID - SharePoint Nodes
    const INVENTORY_ID_SHAREPOINT_APPLICATION   = 105001;
    const INVENTORY_ID_SHAREPOINT_FARM          = 105002;

    // Inventory ID - SQL Nodes
    const INVENTORY_ID_SQL_CLUSTERS                 = 106001;
    const INVENTORY_ID_SQL_AVAILABILITY_GROUPS      = 106002;
    const INVENTORY_ID_SQL_SERVER                   = 106003; //Application Node
    const INVENTORY_ID_SQL_DATABASE                 = 106004;
    const INVENTORY_ID_SQL_ADMINISTRATIVE_DATABASES = 106005;  // Master, Model, MSDB, etc.
    const INVENTORY_ID_SQL_INSTANCE                 = 106006;

    // Inventory ID - VMware Nodes
    const INVENTORY_ID_VMWARE_INVENTORY_ROOT    = 202001;
    const INVENTORY_ID_VMWARE_V_CENTER          = 202002;
    const INVENTORY_ID_VMWARE_DATACENTER        = 202003;
    const INVENTORY_ID_VMWARE_CLUSTER           = 202004;
    const INVENTORY_ID_VMWARE_ESX_SERVER        = 202005;
    const INVENTORY_ID_VMWARE_FOLDER            = 202006;
    const INVENTORY_ID_VMWARE_RESOURCE_POOL     = 202007;
    const INVENTORY_ID_VMWARE_V_APP             = 202008;
    const INVENTORY_ID_VMWARE_VM                = 202009;
    const INVENTORY_ID_VMWARE_TEMPLATE          = 202010;
    const INVENTORY_ID_VMWARE_VM_DISK           = 202011;
    const INVENTORY_ID_VMWARE_WIR_VM            = 202012;  //VMware Windows Instant Recovery VM

    // Inventory ID - XenServer Nodes
    const INVENTORY_ID_XEN_POOL_MASTER          = 203001;
    const INVENTORY_ID_XEN_STANDALONE_SERVER    = 203002;
    const INVENTORY_ID_XEN_SERVER_IN_A_POOL     = 203003;
    const INVENTORY_ID_XEN_FOLDER               = 203004;
    const INVENTORY_ID_XEN_V_APP                = 203005;
    const INVENTORY_ID_XEN_TAG                  = 203006;
    const INVENTORY_ID_XEN_VM                   = 203007;
    const INVENTORY_ID_XEN_VM_DISK              = 203008;
    const INVENTORY_ID_XEN_CUSTOM_TEMPLATE      = 203009;

    // Inventory ID - AHV Nodes
    const INVENTORY_ID_AHV_CLUSTER              = 204001;
    const INVENTORY_ID_AHV_VM                   = 204002;
    const INVENTORY_ID_AHV_VM_DISK              = 203003;

    // Inventory ID - Block Nodes
    const INVENTORY_ID_BLOCK                    = 205001;

    // Inventory Type Family
    const INVENTORY_TYPE_FAMILY_SYSTEM      = 1000;
    const INVENTORY_TYPE_FAMILY_NAVGROUP    = 1001;
    const INVENTORY_TYPE_FAMILY_CLIENT      = 2000;
    const INVENTORY_TYPE_FAMILY_NAS_CLIENT  = 3000;
    const INVENTORY_TYPE_FAMILY_CISCO_UCS   = 101000;
    const INVENTORY_TYPE_FAMILY_EXCHANGE    = 102000;
    const INVENTORY_TYPE_FAMILY_HYPER_V     = 201000;
    const INVENTORY_TYPE_FAMILY_ORACLE      = 104000;
    const INVENTORY_TYPE_FAMILY_SHAREPOINT  = 105000;
    const INVENTORY_TYPE_FAMILY_SQL         = 106000;
    const INVENTORY_TYPE_FAMILY_VMWARE      = 202000;
    const INVENTORY_TYPE_FAMILY_XEN         = 203000;
    const INVENTORY_TYPE_FAMILY_AHV         = 204000;
    const INVENTORY_TYPE_FAMILY_NDMP        = 103000;
    const INVENTORY_TYPE_FAMILY_BLOCK       = 205000;

    // Node Type
    const NODE_TYPE_NONE                = 0;
    const NODE_TYPE_DISK                = 1;
    const NODE_TYPE_VM                  = 2;
    const NODE_TYPE_FOLDER              = 3;
    const NODE_TYPE_RESOURCE_POOL       = 4;
    const NODE_TYPE_HOST                = 5;
    const NODE_TYPE_DATACENTER          = 6;
    const NODE_TYPE_ENVIRONMENT         = 7;
    const NODE_TYPE_UNIVERSE            = 8;
    const NODE_TYPE_CLUSTER             = 9;
    const NODE_TYPE_VM_TEMPLATE_FOLDER  = 10;
    const NODE_TYPE_TAG                 = 11;
    const NODE_TYPE_TEMPLATE            = 12;
    const NODE_TYPE_VAPP                = 13;
    const NODE_TYPE_ROOT_FOLDER         = 14;
    const NODE_TYPE_ROOT_TAG            = 15;
    const NODE_TYPE_XEN                 = 16;  // Not Applicable in our Environment
    const NODE_TYPE_COMPUTER            = 17;
    const NODE_TYPE_VAPP_ROOT           = 18;
    const NODE_TYPE_CDROM               = 19;
    const NODE_TYPE_VM_NIC              = 20;
    const NODE_TYPE_USB                 = 21;
    const NODE_TYPE_SNAPSHOT            = 22;
    const NODE_TYPE_DATASTORE           = 23;
    const NODE_TYPE_JOB                 = 24;
    const NODE_TYPE_DISCOVERED_VM       = 25;
    const NODE_TYPE_GENERIC             = 99;

    //-----NVP Constants-----

    // NVP Item Name Constants
    const NVP_ITEM_NAME_BOOKMARKS                           = "Bookmarks";
    const NVP_ITEM_NAME_COLUMN_COUNT                        = "ColumnCount";
    const NVP_ITEM_NAME_D2D                                 = "D2D";
    const NVP_ITEM_NAME_DASH_ACTIVE_JOB_REFRESH             = "dash_active_job_refresh";
    const NVP_ITEM_NAME_DASH_ALERT_REFRESH                  = "dash_alert_refresh";
    const NVP_ITEM_NAME_DASH_BACKUP_REFRESH                 = "dash_backup_refresh";
    const NVP_ITEM_NAME_DASH_DAILY_FEED_REFRESH             = "dash_daily_feed_refresh";
    const NVP_ITEM_NAME_DASH_FORUM_POSTS_REFRESH            = "dash_forum_posts_refresh";
    const NVP_ITEM_NAME_DASH_REPLICATION_REFRESH            = "dash_replication_refresh";
    const NVP_ITEM_NAME_DASH_RESTORE_REFRESH                = "dash_restore_refresh";
    const NVP_ITEM_NAME_DASH_STORAGE_REFRESH                = "dash_storage_refresh";
    const NVP_ITEM_NAME_DASH_RECOVERY_ASSURANCE_REFRESH     = "dash_recovery_assurance_refresh";
    const NVP_ITEM_NAME_DASHBOARD_SETTINGS                  = "dashboard_settings";
    const NVP_ITEM_NAME_EULA                                = "EULA";
    const NVP_ITEM_NAME_GRANDCLIENT                         = "GrandClient";
    const NVP_ITEM_NAME_HIERARCHY                           = "Hierarchy";
    const NVP_ITEM_NAME_IDENTITY                            = "Identity";
    const NVP_ITEM_NAME_IS_ALIVE                            = "isAlive";
    const NVP_ITEM_NAME_LANGUAGE                            = "language";
    const NVP_ITEM_NAME_LAST_ACTIVE_BACKUP_NUMBER           = "last_active_backup_no";
    const NVP_ITEM_NAME_LAST_CHECKED_BACKUP_NUMBER          = "last_checked_backup_no";
    const NVP_ITEM_NAME_NAVIGATION_GROUPS                   = "NavGroups";
    const NVP_ITEM_NAME_PAGE_JOBS_REFRESH                   = "page_jobs_refresh";
    const NVP_ITEM_NAME_PAGE_PROTECT_REFRESH                = "page_protect_refresh";
    const NVP_ITEM_NAME_PAGE_RESTORE_REFRESH                = "page_restore_refresh";
    const NVP_ITEM_NAME_SAVE_OPEN_NAVIGATION_BRANCHES       = "SaveOpenNavBranches";
    const NVP_ITEM_NAME_SET_SHOW_NAVIGATION_GROUPS_DEFAULT  = "SetShowGroupsDefault";
    const NVP_ITEM_NAME_SETUP_WIZARD_POPUP                  = "SetupWizardPopup";
    const NVP_ITEM_NAME_SHOW_CUSTOMERS                      = "ShowCustomers";
    const NVP_ITEM_NAME_SHOW_GROUPS                         = "ShowGroups";
    const NVP_ITEM_NAME_SHOW_VMS                            = "ShowVMs";
    const NVP_ITEM_NAME_SHOW_EULA                           = "show_eula";
    const NVP_ITEM_NAME_SHOW_HELP_OVERLAY                   = "show_help_overlay";
    const NVP_ITEM_NAME_SHOW_SETUP_WIZARD                   = "show_setup_wizard";
    const NVP_ITEM_NAME_SUBMENU_SETTINGS                    = "SubmenuSettings";
    const NVP_ITEM_NAME_SYSTEM_CLIENT                       = "SystemClient";
    const NVP_ITEM_NAME_VC                                  = "VC";
    const NVP_ITEM_NAME_VIRTUAL_FAILOVER                    = "VirtualFailover";

    // NVP Name Constants
    const NVP_NAME_CLEANUP          = "cleanup";
    const NVP_NAME_CONFIGURATION    = "configuration";
    //const NVP_NAME_FACTORY_DEFAULT  = " !!-factory-default!!";  //I'm not sure this one is correct.  Seen in the database, needs to be validated through the core.
    const NVP_NAME_ROOT             = "root";
    const NVP_NAME_USERS            = "users";
    const NVP_NAME_V_RECOVERY       = "vRecovery";

    // NVP Type Constants
    const NVP_TYPE_NAVIGATION_GROUPS    = "navGroups";
    const NVP_TYPE_PREFERENCES          = "preferences";
    const NVP_TYPE_RRC                  = "RRC";
    const NVP_TYPE_SYSTEM               = "system";
    const NVP_TYPE_USER_PREFERENCE      = "user_preference";

    //-----Joborder Constants-----

    // Joborder Types
    const JOBORDER_TYPE_ARCHIVE         = 'archive';
    const JOBORDER_TYPE_BACKUP          = 'backup';
    const JOBORDER_TYPE_REPLICATION     = 'replication';
    const JOBORDER_TYPE_CERTIFICATION   = 'certification';

    //-----Master.ini Constants-----

    // Master.ini Sections
    const MASTER_INI_SECTION_REPLICATION = 'Replication';

    // Master.ini Replication Values
    const MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT             = 'MaxConcurrent';
    const MASTER_INI_REPLICATION_PREVIOUS_MAXIMUM_CONCURRENT    = 'prevMaxCon';
    const MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT_DEFAULT     = 2;

    //-----Quiesce Constants-----

    // Quiesce Settings
    const QUIESCE_SETTING_APPLICATION_CONSISTENT    = 0;
    const QUIESCE_SETTING_APPLICATION_AWARE         = 1;
    const QUIESCE_SETTING_CRASH_CONSISTENT          = 2;

    // Quiesce Setting Display Name
    const QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_CONSISTENT   = 'Application Consistent';
    const QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_AWARE        = 'Application Aware';
    const QUIESCE_SETTING_DISPLAY_NAME_CRASH_CONSISTENT         = 'Crash Consistent';

    //-----RDR Constants-----

    // User and token suffix
    const RDR_SUFFIX= "_RDR_";

    // Datatypes
    const RDR_JOB = "unitrends.rdr.domain.models.job_ueb, unitrends.rdr.domain";
    const RDR_VM = "unitrends.rdr.domain.models.vm_ueb, unitrends.rdr.domain";
    const RDR_PROFILE = "unitrends.rdr.domain.models.profile_ueb, unitrends.rdr.domain";

    //-----Recovery Constants-----

    // Image Level Instant Recovery (Block IR)
    const HYPERVISOR_TYPE_APPLIANCE                     = "appliance";  // Utilizing Qemu on the Unitrends Appliance
    const HYPERVISOR_DISPLAY_NAME_UNITRENDS_APPLIANCE   = "Unitrends Appliance";  // Utilizing Qemu on the Unitrends Appliance
    
    //-----Report Constants-----

    // Date and Time Formats
    const DATE_FORMAT_DATE_THEN_12HR_TIME   = "M j, Y g:i a";
    const DATE_FORMAT_HRS_MINS_SECS         = "H:i:s";
    const DATE_FORMAT_US                    = "m/d/Y";
    const DATE_TIME_FORMAT_US               = "m/d/Y h:i:s a";
    const TIME_FORMAT_12H                   = "h:i:s a";
    const TIME_FORMAT_12H_NO_MINUTES        = "g:i a";
    const TIME_FORMAT_24H                   = "H:i:s";
    const TIME_FORMAT_24H_NO_MINUTES        = "H:i";

    const DATE_ONE_WEEK_AGO                 = "today -6 days";
    const DATE_TIME_FORMAT_24H              = "m/d/Y H:i:s";

    // International Date Formats
    const DATE_FORMAT_DATE_THEN_24HR_TIME   = "d/m/Y H:i";
    const DATE_FORMAT_FRANCE                = "d/m/Y";
    const DATE_TIME_FORMAT_FRANCE           = "d/m/Y H:i:s";
    const DATE_FORMAT_UK                    = "d/m/Y";
    const DATE_TIME_FORMAT_UK               = "d/m/Y H:i:s";

    // Statuses
    const STATUS_COMPLETED =            'Completed';
    const STATUS_FAILURE =              'Failure';
    const STATUS_IN_PROGRESS =          'In Progress';
    const STATUS_ARCHIVE_IN_PROGRESS =  'Archive In Progress';
    const STATUS_IMPORT_IN_PROGRESS =   'Import In Progress';
    const STATUS_NEEDED =               'Needed';
    const STATUS_NOT_APPLICABLE =       'N/A';
    const STATUS_RUNNING =              'Running';
    const STATUS_SUCCESS =              'Success';
    const STATUS_WAITING =              'Waiting';
    const STATUS_WARNING =              'Warning';
    const STATUS_CANCELLED_ALL =        'Cancelled for all targets';
    const STATUS_NEEDED_SOME =          'Needed/Cancelled, multiple targets';
    const STATUS_DONE_SOME =            'Done/Cancelled, multiple targets';


    //-----Retention Constants-----

    // Retention Type
    const RETENTION_NONE        = 0;
    const RETENTION_TYPICAL     = 1;
    const RETENTION_CUSTOM      = 2;
    const RETENTION_LEGAL_HOLD  = 3;

    //-----Storage Constants-----

    // Storage Type IDs
    const STORAGE_TYPE_ID_INTERNAL_DAS_NOT_MODIFIABLE   = 0;
    const STORAGE_TYPE_ID_ISCI_SAN                      = 1;
    const STORAGE_TYPE_ID_FC_SAN                        = 2;
    const STORAGE_TYPE_ID_AOE_SAN                       = 3;
    const STORAGE_TYPE_ID_NAS                           = 4;
    const STORAGE_TYPE_ID_ADDED_INTERNAL_DAS_MODIFIABLE = 5;

    // Storage Type Names
    const STORAGE_TYPE_NAME_INTERNAL_DAS_NOT_MODIFIABLE     = 'internal';
    const STORAGE_TYPE_NAME_ISCI_SAN                        = 'iscsi';
    const STORAGE_TYPE_NAME_FC_SAN                          = 'fc';
    const STORAGE_TYPE_NAME_AOE_SAN                         = 'aoe';
    const STORAGE_TYPE_NAME_NAS                             = 'nas';
    const STORAGE_TYPE_NAME_ADDED_INTERNAL_DAS_MODIFIABLE   = 'added_disks';
    const STORAGE_TYPE_NAME_ADDED_INTERNAL                  = 'added_internal';


    // Storage Type Display Names
    const STORAGE_TYPE_DISPLAY_NAME_INTERNAL_DAS_NOT_MODIFIABLE     = 'Internal';
    const STORAGE_TYPE_DISPLAY_NAME_ISCI_SAN                       = 'iSCSI';
    const STORAGE_TYPE_DISPLAY_NAME_FC_SAN                         = 'FC';
    const STORAGE_TYPE_DISPLAY_NAME_AOE_SAN                        = 'AOE';
    const STORAGE_TYPE_DISPLAY_NAME_NAS                            = 'NAS';
    const STORAGE_TYPE_DISPLAY_NAME_ADDED_INTERNAL_DAS_MODIFIABLE  = 'Direct-Attached Disk';

    // Storage Usage
    const STORAGE_USAGE_ARCHIVE = 'archive';
    const STORAGE_USAGE_BACKUP  = 'backup';
    const STORAGE_USAGE_SOURCE  = 'source';
    const STORAGE_USAGE_VAULT   = 'vault';

    //-----System Constants-----

    // System Identity Constants
    const SYSTEM_IDENTITY_BACKUP_SYSTEM     = 'DPU';
    const SYSTEM_IDENTITY_MANAGED_SYSTEM    = 'MDPU';
    const SYSTEM_IDENTITY_VAULT             = 'DPV';
    const SYSTEM_IDENTITY_CROSS_VAULT       = 'DPXV';

    // System Role Constants
    const SYSTEM_ROLE_DPU                               = 'DPU';
    const SYSTEM_ROLE_MANAGER                           = 'Manager';
    const SYSTEM_ROLE_VAULT                             = 'Vault';
    const SYSTEM_ROLE_MANAGED_DPU                       = 'Managed DPU';
    const SYSTEM_ROLE_DPU_CONFIGURED_FOR_VAULTING       = 'DPU Configured for Vaulting';
    const SYSTEM_ROLE_REPLICATION_SOURCE                = 'Replication Source';
    const SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE    = 'Non-managed Replication Source';
    //const SYSTEM_ROLE_PENDING_REPLICATION_SOURCE= '';

    // System Role Display Name Constants
    const SYSTEM_ROLE_DISPLAY_NAME_BACKUP_SYSTEM                    = 'Backup System';
    const SYSTEM_ROLE_DISPLAY_NAME_MANAGER                          = 'Manager';
    const SYSTEM_ROLE_DISPLAY_NAME_TARGET                           = 'Target';
    const SYSTEM_ROLE_DISPLAY_NAME_MANAGED_DPU                      = 'Managed DPU';
    const SYSTEM_ROLE_DISPLAY_NAME_DPU_CONFIGURED_FOR_VAULTING      = 'DPU Configured for Vaulting';
    const SYSTEM_ROLE_DISPLAY_NAME_REPLICATION_SOURCE               = 'Replication Source';
    const SYSTEM_ROLE_DISPLAY_NAME_NON_MANAGED_REPLICATION_SOURCE   = 'Non-Managed Replication Source';
    const SYSTEM_ROLE_DISPLAY_NAME_PENDING_REPLICATION_SOURCE       = 'Pending Replication Source';

    //System Status Constants
    const SYSTEM_STATUS_ACCEPTED        = 'accepted';
    const SYSTEM_STATUS_AVAILABLE       = 'available';
    const SYSTEM_STATUS_COMPLETE        = 'complete';
    const SYSTEM_STATUS_FAILED          = 'failed';
    const SYSTEM_STATUS_NOT_AVAILABLE   = 'not available';
    const SYSTEM_STATUS_PENDING         = 'pending';
    const SYSTEM_STATUS_REJECTED        = 'rejected';
    const SYSTEM_STATUS_SUSPENDED       = 'suspended';

    //Flex UI Name
    const FLASH_UI_NAME         = 'Legacy UI';

    const WIR_UNKNOWN_CLIENT_ID = 'virtual';  //WIR Hyper-V Hosts

    const REPLICAS_CLIENT_ID = 'replica';  //replica Hyper-V Hosts

    // WIR Supported Hypervisor Constants
    const WIR_SUPPORTED_HYPERVISOR_UNITRENDS_APPLIANCE  = 'Unitrends appliance';
    const WIR_SUPPORTED_HYPERVISOR_HYPER_V_HOST         = 'Hyper-V host';
    const WIR_SUPPORTED_HYPERVISOR_VMWARE_HOST          = 'VMware host';

    //Privilege level
    const PRIV_NONE         = 0;
    const PRIV_MONITOR      = 1;
    const PRIV_MANAGE       = 2;
    const PRIV_ADMINISTER   = 3;
    const PRIV_SUPERUSER    = 99;

    //WIR states constants
    const REPLICAS_STATE_NEW            = 'new';
    const REPLICAS_STATE_RESTORE        = 'restore';
    const REPLICAS_STATE_IDLE           = 'idle';
    const REPLICAS_STATE_AUDIT          = 'audit';
    const REPLICAS_STATE_LIVE           = 'live';
    const REPLICAS_STATE_OFF            = 'off';
    const REPLICAS_STATE_HALTED         = 'halted';
    const REPLICAS_STATE_DESTROY        = 'destroy';
    const REPLICAS_STATE_VERIFY         = 'verify';
    const REPLICAS_STATE_CREATE         = 'create';
    const REPLICAS_STATE_UNINITIALIZED  = 'uninitialized';
    const REPLICAS_STATE_AUDIT_RDR      = 'audit-rdr';

    // Return codes for REST APIs
    const AJAX_RESULT_SUCCESS           = '0';
    const AJAX_RESULT_WARNING           = '1';
    const AJAX_RESULT_ERROR             = '2';  // not used as HTTP code 500 is returned to the caller on error.
    const AJAX_RESULT_PARTIAL_SUCCESS   = '3';
}

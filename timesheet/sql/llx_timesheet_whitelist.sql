-- ===================================================================
-- Copyright (C) 2013  Alexandre Spangaro <alexandre.spangaro@gmail.com>
-- Copyright (C) 2015  Patrick Delcroix <pmpdelcroix@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ===================================================================
-- TS Revision 1.5.0

-- this table is used to store the timesheet favorit

CREATE TABLE llx_timesheet_whitelist 
(
rowid                 integer NOT NULL AUTO_INCREMENT,
fk_user               integer NOT NULL,
fk_project            integer NOT NULL,   
fk_project_task       integer,               
subtask               BOOLEAN default FALSE,
date_start            date default NULL,
date_end              date default NULL,
PRIMARY KEY (rowid)
) 
ENGINE=innodb;


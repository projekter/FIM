{ =============================================================================
  Copyright (c) 2014, Benjamin Desef
  All rights reserved.

  This program is distributed in the hope that it will be useful, but WITHOUT
  ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
  FOR A PARTICULAR PURPOSE.

  This program is licensed under the Creative Commons
  Attribution-NonCommercial-ShareAlike 4.0 International License. You should
  have received a copy of this license along with this program. If not, see
  see <http://creativecommons.org/licenses/by-nc-sa/4.0/>.
  ============================================================================ }

Program FileHelper;

{$APPTYPE CONSOLE}
{$R *.res}

Uses
   System.SysUtils, Winapi.Windows, ShellAPI,
   uBase64Codec In 'uBase64Codec.pas';

(*
  //  It would be possible to query the is_readable/is_writable status with this
  function. However, it does not check for ACL privileges! All it does is
  checking for the readonly flag - which we could do in a better way.
  Function _waccess_s(Path: PChar; Mode: Integer): Integer; Cdecl;
  External 'msvcrt.dll';

  Const
  EACCES          = 13;
  ENOENT          = 2;
  EINVAL          = 22;
  WritePermission = 2;
  ReadPermission  = 4;
*)

Function TimeStampHumanToUnix(Const HumanStamp: TDateTime): Cardinal;
Var
   iTime:          Integer;
   hh, mm, ss, ms: Word;
Const
   FirstOf1970 = 25569;
   // [s] seit 30.12.1899 bis 01.01.1970 (WinZeit -> UnixZeit)
   OneDay    = 86400; // in [s]
   OneHour   = 3600; // in [s]
   OneMinute = 60; // in [s]
Begin
   DecodeTime(HumanStamp, hh, mm, ss, ms);

   { Tage seit 1.1.1970 in Sekunden }
   iTime := (Trunc(HumanStamp) - FirstOf1970) * OneDay;
   { Stunden und Minuten und Sekunden aufaddieren }
   iTime := iTime + (hh * OneHour) + (mm * OneMinute) + ss;
   If iTime < 0 Then
      iTime := 0;
   Result := iTime;
End;

Function DecodeParameter(Const Param: String): String;
Var
   Output: PAnsiChar;
Begin
   Base64Decode(PAnsiChar(AnsiString(Param)), Output);
   Result := String(UTF8String(Output));
End;

Var
   S:     String;
   DTI:   TDateTimeInfoRec;
   FD:    TWin32FileAttributeData Absolute DTI;
   SI:    TSHFileInfo;
   SR:    TSearchRec;
   Size:  Packed Record
      Case Boolean Of
         True: (Low, High: DWORD);
         False: (Val: UInt64);
      End;

Begin
   S := DecodeParameter(ParamStr(1));
   SetCurrentDir(DecodeParameter(ParamStr(2)));
   If (Not DirectoryExists(S)) And (Not FileExists(S)) Then Begin
      WriteLn('notfound');
      Halt(1);
   End;

   S := ExpandFileName(S);
   If AnsiLastChar(S) = '\' Then
      Delete(S, Length(S), 1);
   WriteLn(Base64Encode(UTF8Encode(S)));
   FileGetSymLinkTarget(S, S);
   S := ExpandFileName(S);
   If AnsiLastChar(S) = '\' Then
      Delete(S, Length(S), 1);
   // FileGetDateTimeInfo is intended to only return a TDateTimeInfoRec.
   // However, due to the implementation which relies on GetFileAttributesEx,
   // a complete TWin32FileAttributeData record will be filled.
   FileGetDateTimeInfo(S, DTI, False);
   WriteLn(Base64Encode(UTF8Encode(S)));
   WriteLn(TimeStampHumanToUnix(DTI.CreationTime));
   WriteLn(TimeStampHumanToUnix(DTI.LastAccessTime));
   WriteLn(TimeStampHumanToUnix(DTI.TimeStamp));
   If SHGetFileInfo(PChar(S), 0, SI, 0, SHGFI_EXETYPE) = 0 Then
      WriteLn('0')
   Else
      WriteLn('1');

   Size.High := FD.nFileSizeHigh;
   Size.Low := FD.nFileSizeLow;
   WriteLn(Size.Val);

   If (FD.dwFileAttributes And faReadOnly) <> 0 Then
      WriteLn('1')
   Else
      WriteLn('0');

   If (FD.dwFileAttributes And faDirectory) = 0 Then
      WriteLn('file')
   Else Begin
      WriteLn('dir');
      If FindFirst(S + '\*', faAnyFile, SR) = 0 Then
         Try
            Repeat
               If (SR.Name <> '.') And (SR.Name <> '..') Then
                  WriteLn(Base64Encode(UTF8Encode(S + '\' + SR.Name)));
            Until FindNext(SR) <> 0;
         Finally

            System.SysUtils.FindClose(SR);
         End;
   End;

End.

@php
use App\Support\Latex;
$e = fn ($value) => new \Illuminate\Support\HtmlString(Latex::escape((string) $value));
$u = fn ($value) => new \Illuminate\Support\HtmlString(strtr((string) $value, ['\\' => '\%5C', '{' => '\%7B', '}' => '\%7D', '%' => '\%', '#' => '\#', '~' => '\%7E']));
$linkAccent = Latex::accessibleAccent((string) $cv['accent']);
$v = function ($value) use ($u) {
    $visible = preg_replace('/^https?:\/\//i', '', (string) $value);

    if (preg_match('/#cv=([A-Za-z0-9]+)$/', $visible, $match, PREG_OFFSET_CAPTURE)) {
        $token = $match[1][0];
        $prefix = substr($visible, 0, $match[1][1]);
        $chunks = str_split($token, 12);
        $parts = ['\\nolinkurl{'.(string) $u($prefix.array_shift($chunks)).'}'];
        foreach ($chunks as $chunk) {
            $parts[] = '\\nolinkurl{'.(string) $u($chunk).'}';
        }

        return new \Illuminate\Support\HtmlString(implode('\\allowbreak{}', $parts));
    }

    return new \Illuminate\Support\HtmlString('\\nolinkurl{'.(string) $u($visible).'}');
};
@endphp
% !TeX program = lualatex
\documentclass[9pt,a4paper]{article}
\usepackage[a4paper,top=17mm,bottom=18mm,left=17mm,right=17mm,footskip=9mm,includefoot]{geometry}
\usepackage{fontspec,xcolor,graphicx,hyperref,tikz,array,tabularx,paracol,needspace}
\usetikzlibrary{calc}
\setmainfont{Fira Sans}[Path=resources/fonts/,Extension=.ttf,UprightFont=FiraSans-Regular,BoldFont=FiraSans-Bold]
\definecolor{accent}{HTML}{@php echo strtoupper(ltrim($cv['accent'], '#')); @endphp}
\definecolor{linkaccent}{HTML}{@php echo $linkAccent; @endphp}
\definecolor{cvtext}{HTML}{111A16}\definecolor{muted}{HTML}{52605A}\definecolor{rule}{HTML}{CCD8D2}\definecolor{paper}{HTML}{FFFFFF}
\pagecolor{white}\color{cvtext}
\hypersetup{colorlinks=true,urlcolor=linkaccent,linkcolor=linkaccent,pdfauthor={ {{ $e($cv['name']) }} },pdftitle={ {{ $e($cv['name']) }} · Lebenslauf @if($cv['profile']) · {{ $e($cv['profile']['title']) }}@endif }}
\setlength{\parindent}{0pt}\setlength{\parskip}{0pt}\setlength{\columnsep}{7mm}\emergencystretch=1em
\newcommand{\cvoutlinkicon}{\tikz[baseline=-.18em,x=.58em,y=.58em]\draw[linkaccent,line width=.075em,line cap=round,line join=round] (0,0)--(1,1)--(.56,1) (1,1)--(1,.56);}
\newcommand{\cvlink}[2]{\href{#1}{\textcolor{linkaccent}{#2}}}
\newcommand{\cvoutlink}[2]{\href{#1}{\textcolor{linkaccent}{#2\mbox{\hspace{.13em}\cvoutlinkicon}}}}
\newcommand{\cvurl}[2]{\href{#1}{\textcolor{linkaccent}{\textbf{#2\mbox{\hspace{.13em}\cvoutlinkicon}}}}}
\makeatletter
\def\ps@cv{%
  \def\@oddhead{}\let\@evenhead\@oddhead
  \def\@oddfoot{%
    \hbox to \textwidth{%
      \parbox[b]{.22\textwidth}{\fontsize{6}{7}\selectfont\color{muted}{{ $e($cv['name']) }} · Lebenslauf}%
      \hfil
      \parbox[b]{.68\textwidth}{\raggedleft\fontsize{7.1}{8.5}\selectfont\color{muted}\textbf{Interaktive Version:} \cvurl{ {{ $u($cv['interactive_url']) }} }{ {{ $v($cv['interactive_url']) }} }\hspace{.7em}{\fontsize{6.5}{7.5}\selectfont\color{muted}\thepage}}%
    }%
  }%
  \let\@evenfoot\@oddfoot
}
\makeatother\pagestyle{cv}
\newcommand{\sectiontitle}[1]{\Needspace{30mm}\vspace{2.2mm}{\fontsize{15}{16}\selectfont\bfseries #1}\par\nopagebreak\vspace{1mm}{\color{rule}\rule{\linewidth}{.45pt}}\par\nopagebreak\vspace{.4mm}}
\newcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{11}{13}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{30mm}@{}}\strut\textbf{\cvoutlink{#4}{#1}}&\strut{\fontsize{8.2}{13}\selectfont\color{muted}\mbox{#2}}\\[-.8mm]\end{tabularx}\par{\fontsize{9}{11}\selectfont\bfseries #3}\par\vspace{.8mm}{\fontsize{9.5}{12.2}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{4mm}}
\newcommand{\sidebarhead}[1]{\vspace{1mm}{\fontsize{7}{8}\selectfont\bfseries\MakeUppercase{#1}}\par\vspace{1.2mm}}
\newcommand{\sidevalue}[1]{\begingroup\fontsize{8.4}{10.5}\selectfont\color{muted}\raggedright #1\par\endgroup\vspace{1.2mm}}
\begin{document}\thispagestyle{cv}
\begin{tikzpicture}[x=1mm,y=1mm]
  \def\W{176}\def\H{46}
  \fill[accent!18] (0,0) rectangle (\W,\H);
  \fill[paper] (0,0)--(0,18)..controls(35,21)and(64,25)..(95,17)..controls(124,10)and(142,7)..(157,0)--cycle;
  \fill[accent!48] (126,0)--(\W,0)--(\W,20)..controls(169,15)and(151,11)..(141,6)--cycle;
  \draw[cvtext,line width=.6pt] (0,0) rectangle (\W,\H);
  @if($cv['profile'])\node[anchor=west,inner sep=0pt,text=cvtext] at (11,37) {\fontsize{7.5}{8}\selectfont\bfseries {{ $e($cv['profile']['title']) }}};@endif
  \node[anchor=west,inner sep=0pt] at (11,19) {\fontsize{32}{32}\selectfont\bfseries {{ $e($cv['name']) }}};
  \node[anchor=west,inner sep=0pt] at (11,8) {\fontsize{7}{8}\selectfont\bfseries {{ $e($cv['location']) }}};
  \node[anchor=west,inner sep=0pt] at (73,8) {\fontsize{7.6}{9}\selectfont\cvurl{ {{ $u($cv['interactive_url']) }} }{ {{ $v($cv['interactive_url']) }} }};
  @if($cv['photo'])\begin{scope}\clip (151,23) circle (15mm);\node[inner sep=0pt] at (151,23){\includegraphics[height=34mm]{ {{ $e($cv['photo']) }} }};\end{scope}\draw[cvtext,line width=.6pt] (151,23) circle (15mm);@endif
\end{tikzpicture}
\vspace{4mm}
\columnratio{.27}\begin{paracol}{2}
\sidebarhead{Kontakt}
@if($cv['contact']['public_email'])\sidevalue{E-Mail: \cvlink{mailto:{{ $u($cv['contact']['public_email']) }}}{ {{ $e($cv['contact']['public_email']) }} }}@endif
@if($cv['contact']['email'])\sidevalue{Private E-Mail: \cvlink{mailto:{{ $u($cv['contact']['email']) }}}{ {{ $e($cv['contact']['email']) }} }}@else\sidevalue{Private E-Mail: Geschützte Angabe}@endif
@if($cv['contact']['phone'])\sidevalue{ {{ $e($cv['contact']['phone']) }} }@else\sidevalue{Geschützte Angabe}@endif
@if($cv['contact']['address'])\sidevalue{ {{ $e($cv['contact']['address']) }} }@else\sidevalue{Geschützte Angabe}@endif
\sidevalue{ {{ $e($cv['location']) }} }
\sidebarhead{Interaktiver Lebenslauf}
\sidevalue{\begingroup\fontsize{8.6}{11}\selectfont\bfseries\cvurl{ {{ $u($cv['interactive_url']) }} }{ {{ $v($cv['interactive_url']) }} }\endgroup}
@if($cv['about'])\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Über mich}\sidevalue{ {{ $e($cv['about']) }} }@endif
@foreach($cv['knowledge'] as $group)\vspace{1.2mm}\sidebarhead{ {{ $e(mb_strtoupper($group['category'] ?? '')) }} }@foreach(($group['items'] ?? []) as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\{\scriptsize {{ $e($item['context']) }} }@endif}@endforeach @endforeach
@if($cv['soft_skills'])\sidebarhead{Soft Skills}
@foreach($cv['soft_skills'] as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\ {{ $e($item['context']) }}@endif }@endforeach
@endif
\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Online}
@if($cv['contact']['website'])\sidevalue{\cvoutlink{ {{ $u($cv['contact']['website']) }} }{Website}}@endif
@if($cv['contact']['github'])\sidevalue{\cvoutlink{ {{ $u($cv['contact']['github']) }} }{GitHub}}@endif
@if($cv['contact']['codeberg'])\sidevalue{\cvoutlink{ {{ $u($cv['contact']['codeberg']) }} }{Codeberg}}@endif
\switchcolumn
\sectiontitle{Erfahrung}
@foreach($cv['experience'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['organisation']) }} }{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
\sectiontitle{Bildung}
@foreach($cv['education'] as $item)\entry{ {{ $e($item['programme'] ?: $item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['title']) }} }{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
@if($cv['projects'])\sectiontitle{Projekte}
@foreach($cv['projects'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{}{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
@endif
@if($cv['publications'])\sectiontitle{Veröffentlichungen}
@foreach($cv['publications'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['type']) }} }{ {{ $u($item['url']) }} }{}@endforeach
@endif
\end{paracol}
\end{document}

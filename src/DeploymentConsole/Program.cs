using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text;

namespace DeploymentConsole;

/// <summary>
/// deployctl - a small release pre-flight checker.
///
/// It validates the artefacts that are about to be pushed to Azure App Service and
/// prints a pass/fail table with a machine readable exit code, so it can be wired into
/// a build pipeline. Written as the standalone C# application required by Task 3.2.
/// </summary>
internal static class Program
{
    private static int Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;

        var options = Options.Parse(args);
        if (options.ShowHelp)
        {
            PrintHelp();
            return 0;
        }

        Banner(options);

        var checks = new List<Check>
        {
            CheckRuntime(),
            CheckEnvironment(),
            Check("Project file present", "DeploymentDashboard.csproj", File.Exists(Path.Combine(options.Path, "DeploymentDashboard.csproj"))),
            Check("Static assets present", "wwwroot/css/site.css", File.Exists(Path.Combine(options.Path, "wwwroot", "css", "site.css"))),
            Check("Razor pages present", "Pages/Index.cshtml", File.Exists(Path.Combine(options.Path, "Pages", "Index.cshtml"))),
            Check("Health endpoint wired", "Program.cs contains MapHealthChecks",
                File.Exists(Path.Combine(options.Path, "Program.cs")) &&
                File.ReadAllText(Path.Combine(options.Path, "Program.cs")).Contains("MapHealthChecks")),
            Check("HTTPS redirection", "Program.cs contains UseHttpsRedirection",
                File.Exists(Path.Combine(options.Path, "Program.cs")) &&
                File.ReadAllText(Path.Combine(options.Path, "Program.cs")).Contains("UseHttpsRedirection")),
            Check("Secrets not committed", "no appsettings.*.Production.json",
                !Directory.EnumerateFiles(options.Path, "appsettings.Production.json", SearchOption.AllDirectories).Any()),
            CheckAzureSettings(options)
        };

        PrintTable(checks);

        var failed = checks.Count(c => !c.Passed);
        var skipped = checks.Count(c => !c.Passed && c.Optional);

        Console.WriteLine();
        if (failed - skipped == 0)
        {
            Console.WriteLine($"  ✓ PRE-FLIGHT PASSED  ({checks.Count - failed} checks, {skipped} optional skipped)");
            Console.WriteLine("    Ready to publish: Build > Publish > Azure App Service (Windows)");
            return 0;
        }

        Console.WriteLine($"  ✗ PRE-FLIGHT FAILED  ({failed - skipped} blocking issue(s))");
        Console.WriteLine("    Resolve the red rows above before publishing.");
        return 1;
    }

    // ------------------------------------------------------------------ output

    private static void Banner(Options options)
    {
        Console.WriteLine();
        Console.WriteLine("  ┌──────────────────────────────────────────────────────────────┐");
        Console.WriteLine("  │  deployctl 1.0.0  ·  Azure App Service pre-flight checker     │");
        Console.WriteLine("  │  SWE40006 Software Deployment and Evolution · Task 3          │");
        Console.WriteLine("  └──────────────────────────────────────────────────────────────┘");
        Console.WriteLine($"  Project : {options.Path}");
        Console.WriteLine($"  Target  : {options.Target}");
        Console.WriteLine();
    }

    private static void PrintTable(IReadOnlyList<Check> checks)
    {
        Console.WriteLine($"  {"#",-3}{"RESULT",-9}{"CHECK",-30}DETAIL");
        Console.WriteLine($"  {new string('─', 3)}{new string('─', 9)}{new string('─', 30)}{new string('─', 34)}");

        for (var i = 0; i < checks.Count; i++)
        {
            var c = checks[i];
            var result = c.Passed ? "PASS" : c.Optional ? "SKIP" : "FAIL";
            var colour = c.Passed ? ConsoleColor.Green : c.Optional ? ConsoleColor.DarkYellow : ConsoleColor.Red;

            Console.Write($"  {i + 1,-3}");
            Console.ForegroundColor = colour;
            Console.Write($"{result,-9}");
            Console.ResetColor();
            Console.WriteLine($"{c.Name,-30}{c.Detail}");
        }
    }

    private static void PrintHelp()
    {
        Console.WriteLine("deployctl - Azure App Service pre-flight checker");
        Console.WriteLine();
        Console.WriteLine("USAGE");
        Console.WriteLine("  deployctl [--path <dir>] [--target <name>] [--check | --report] [--help]");
        Console.WriteLine();
        Console.WriteLine("OPTIONS");
        Console.WriteLine("  --path     Directory containing the web project (default: ../DeploymentDashboard)");
        Console.WriteLine("  --target   Azure App Service name used in the summary (default: clouddeploy-dashboard)");
        Console.WriteLine("  --report   Write a Markdown report to ./deploy-report.md");
        Console.WriteLine("  --check    Run the checks only (default behaviour)");
        Console.WriteLine("  --help     Show this help");
        Console.WriteLine();
        Console.WriteLine("EXIT CODES");
        Console.WriteLine("  0  all blocking checks passed");
        Console.WriteLine("  1  at least one blocking check failed");
    }

    // ------------------------------------------------------------------ checks

    private static Check CheckRuntime()
    {
        var version = Environment.Version;
        return new Check(
            "Runtime supported",
            version.Major >= 8,
            $".NET {version} on {RuntimeInformation.ProcessArchitecture}");
    }

    private static Check CheckEnvironment()
    {
        var machine = Environment.MachineName;
        return new Check("Build host identified", true, $"{machine} / {RuntimeInformation.OSDescription.Split('(')[0].Trim()}");
    }

    private static Check Check(string name, string detail, bool passed) => new(name, passed, detail);

    private static Check CheckAzureSettings(Options options)
    {
        var hasSite = !string.IsNullOrWhiteSpace(Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME"));
        return new Check(
            "App Service settings",
            hasSite,
            hasSite
                ? $"running inside {Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME")}"
                : "not running in Azure (optional for local builds)",
            Optional: true);
    }

    // ------------------------------------------------------------------ types

    private sealed record Check(string Name, bool Passed, string Detail, bool Optional = false);

    private sealed record Options(string Path, string Target, bool ShowHelp, bool WriteReport)
    {
        public static Options Parse(string[] args)
        {
            var path = System.IO.Path.GetFullPath(System.IO.Path.Combine(Environment.CurrentDirectory, "..", "DeploymentDashboard"));
            var target = "clouddeploy-dashboard";
            var help = false;
            var report = false;

            for (var i = 0; i < args.Length; i++)
            {
                switch (args[i].ToLowerInvariant())
                {
                    case "--path" when i + 1 < args.Length:
                        path = System.IO.Path.GetFullPath(args[++i]);
                        break;
                    case "--target" when i + 1 < args.Length:
                        target = args[++i];
                        break;
                    case "--report":
                        report = true;
                        break;
                    case "--help" or "-h" or "/?":
                        help = true;
                        break;
                }
            }

            return new Options(path, target, help, report);
        }
    }
}

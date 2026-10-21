using DeploymentDashboard.Models;
using DeploymentDashboard.Services;

namespace DeploymentDashboard.Tests;

/// <summary>
/// Tests for the in-memory checklist store: state transitions, input hygiene and
/// thread safety. The store is a singleton in the running web application, so it
/// is hit concurrently by every request on the instance.
/// </summary>
public sealed class TaskStoreTests
{
    [Fact]
    public void NewStore_IsSeededWithTheDeploymentChecklist()
    {
        var store = new InMemoryTaskStore();

        var tasks = store.All();

        Assert.Equal(7, tasks.Count);
        Assert.Contains(tasks, t => t.Status == ChecklistStatus.Done);
        Assert.All(tasks, t => Assert.False(string.IsNullOrWhiteSpace(t.Title)));
    }

    [Fact]
    public void Seed_IsOrderedByStatusThenNewestFirst()
    {
        var store = new InMemoryTaskStore();

        var statuses = store.All().Select(t => t.Status).ToList();

        Assert.Equal(statuses.OrderBy(s => s), statuses);
    }

    [Fact]
    public void Add_TrimsInputAndDefaultsOwner()
    {
        var store = new InMemoryTaskStore();

        var created = store.Add("  Configure a custom domain  ", "  via portal  ", "   ");

        Assert.Equal("Configure a custom domain", created.Title);
        Assert.Equal("via portal", created.Notes);
        Assert.Equal("Student", created.Owner);
        Assert.Equal(ChecklistStatus.Planned, created.Status);
        Assert.Null(created.CompletedUtc);
    }

    [Fact]
    public void Add_ConvertsWhitespaceNotesToNull()
    {
        var store = new InMemoryTaskStore();

        var created = store.Add("Add health probe", "   ", "Student");

        Assert.Null(created.Notes);
    }

    [Fact]
    public void Advance_CyclesThroughEveryStatusAndStampsCompletion()
    {
        var store = new InMemoryTaskStore();
        var task = store.Add("Enable HTTPS only", null, "Student");

        Assert.True(store.Advance(task.Id));
        Assert.Equal(ChecklistStatus.InProgress, store.All().Single(t => t.Id == task.Id).Status);

        Assert.True(store.Advance(task.Id));
        var completed = store.All().Single(t => t.Id == task.Id);
        Assert.Equal(ChecklistStatus.Done, completed.Status);
        Assert.NotNull(completed.CompletedUtc);

        Assert.True(store.Advance(task.Id));
        var reopened = store.All().Single(t => t.Id == task.Id);
        Assert.Equal(ChecklistStatus.Planned, reopened.Status);
        Assert.Null(reopened.CompletedUtc);
    }

    [Fact]
    public void Advance_ReturnsFalseForUnknownId()
    {
        var store = new InMemoryTaskStore();

        Assert.False(store.Advance(Guid.NewGuid()));
    }

    [Fact]
    public void Remove_DeletesTheTaskAndIsIdempotent()
    {
        var store = new InMemoryTaskStore();
        var task = store.Add("Temporary task", null, "Student");

        Assert.True(store.Remove(task.Id));
        Assert.False(store.Remove(task.Id));
        Assert.DoesNotContain(store.All(), t => t.Id == task.Id);
    }

    [Fact]
    public void Store_HandlesConcurrentWrites()
    {
        var store = new InMemoryTaskStore();

        Parallel.For(0, 200, i => store.Add($"Concurrent task {i}", null, "Student"));

        Assert.Equal(207, store.All().Count);
    }
}

describe('test backend booking', () => {
    beforeEach( function() {
        cy.visit( '/wp-login.php' );
        cy.wait( 1000 );
        cy.get( '#user_login' ).type( Cypress.env( "wpAdmin" ) );
        cy.get( '#user_pass' ).type( Cypress.env( "wpPassword" ) );
        cy.get( '#wp-submit' ).click();
    } );
  it('can create entirely new admin booking', () => {
    let today = new Date()
    let inTwoDays = new Date()
    inTwoDays.setDate(today.getDate() + 2)
    const expectedStartDate = today.toLocaleDateString()
    const expectedEndDate = inTwoDays.toLocaleDateString()
    cy.visit('/wp-admin/post-new.php?post_type=cb_booking')
    cy.get('#title').type('Test booking')
    //TODO: get this data from fixtures
    cy.get('#item-id').select('BasicTest - NoAdmin')
    cy.get('#location-id').select('BasicTest - Köln Dom LocMap NoAdmin')
    cy.get('#full-day').check()
    cy.get('#repetition-start_date').clear().type(expectedStartDate)
    //click somewhere outside of the datepicker to close it
    cy.get('body').click(0,0)
    cy.get('#repetition-end_date').clear().type(expectedEndDate)
    cy.get('body').click(0,0)
    //click post button
    cy.get('#cb-submit-booking').click()
    cy.get('#message > p').contains('Post updated.')
    cy.get('#post-status-display').contains('Confirmed')

    //let's go to the frontend booking calendar and check that our item exists there
    //set date to today (this is probably unnecessary, but just to be sure)
    cy.clock(today.getTime())
    cy.visit('/?cb_item=basictest-noadmin&cb-location=32')
    cy.get('.is-today').should('have.class', 'is-booked')
  })

  after( function () {
    cy.visit('/wp-admin/edit.php?post_type=cb_booking')
    cy.get('.submitdelete').click({multiple: true, force: true})
  })
})